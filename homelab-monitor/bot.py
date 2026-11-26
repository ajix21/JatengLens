#!/usr/bin/env python3
"""
Homelab Telegram Monitor Bot (Ultra-Lightweight, Zero External Dependencies)
Monitors Host CPU, RAM, Swap, Disk, Temperature, Top Processes, and Docker Containers.
Designed for low-RAM Linux homelabs (~15 MB RAM footprint).
"""

import json
import os
import re
import shutil
import socket
import subprocess
import sys
import threading
import time
import urllib.error
import urllib.parse
import urllib.request
from datetime import datetime
from pathlib import Path
from typing import Dict, List, Optional, Tuple


# ── Config Loader (.env without python-dotenv) ───────────────────────────────

def load_dotenv(env_path: Path) -> None:
    if not env_path.is_file():
        return
    for raw_line in env_path.read_text(encoding="utf-8").splitlines():
        line = raw_line.strip()
        if not line or line.startswith("#") or "=" not in line:
            continue
        key, val = line.split("=", 1)
        key = key.strip()
        val = val.strip().strip('"').strip("'")
        if key and key not in os.environ:
            os.environ[key] = val


BASE_DIR = Path(__file__).resolve().parent
load_dotenv(BASE_DIR / ".env")

BOT_TOKEN = os.environ.get("TELEGRAM_BOT_TOKEN", "").strip()
ALLOWED_CHAT_IDS = {
    cid.strip()
    for cid in os.environ.get("ALLOWED_CHAT_IDS", "").split(",")
    if cid.strip()
}
HOST_LABEL = os.environ.get("HOST_LABEL", socket.gethostname()).strip()

# Alert thresholds & intervals
ALERT_ENABLED = os.environ.get("ALERT_ENABLED", "true").lower() in ("1", "true", "yes")
CHECK_INTERVAL_SEC = max(15, int(os.environ.get("CHECK_INTERVAL_SEC", "60")))
ALERT_COOLDOWN_SEC = max(60, int(os.environ.get("ALERT_COOLDOWN_SEC", "900")))
CPU_THRESHOLD = float(os.environ.get("CPU_THRESHOLD", "90"))
RAM_THRESHOLD = float(os.environ.get("RAM_THRESHOLD", "85"))
SWAP_THRESHOLD = float(os.environ.get("SWAP_THRESHOLD", "85"))
DISK_THRESHOLD = float(os.environ.get("DISK_THRESHOLD", "85"))

CONTAINER_NAME_RE = re.compile(r"^[a-zA-Z0-9][a-zA-Z0-9_.-]{0,127}$")


# ── Helpers & Formatting ─────────────────────────────────────────────────────

def progress_bar(percent: float, length: int = 10) -> str:
    pct = max(0.0, min(100.0, percent))
    filled = round((pct / 100.0) * length)
    return "█" * filled + "░" * (length - filled)


def status_emoji(percent: float, warn: float = 75.0, crit: float = 85.0) -> str:
    if percent >= crit:
        return "🔴"
    if percent >= warn:
        return "🟡"
    return "🟢"


def fmt_bytes(num_bytes: float) -> str:
    for unit in ("B", "KB", "MB", "GB", "TB"):
        if abs(num_bytes) < 1024.0:
            return f"{num_bytes:.1f} {unit}"
        num_bytes /= 1024.0
    return f"{num_bytes:.1f} PB"


def html_escape(text: str) -> str:
    return (
        text.replace("&", "&amp;")
        .replace("<", "&lt;")
        .replace(">", "&gt;")
    )


def run_cmd(args: List[str], timeout: int = 10) -> Tuple[int, str, str]:
    try:
        proc = subprocess.run(
            args,
            capture_output=True,
            text=True,
            timeout=timeout,
            shell=False,
        )
        return proc.returncode, proc.stdout.strip(), proc.stderr.strip()
    except FileNotFoundError:
        return 127, "", f"Command not found: {args[0]}"
    except subprocess.TimeoutExpired:
        return 124, "", "Command timed out"
    except Exception as exc:
        return 1, "", str(exc)


# ── System Metrics Collectors (Linux /proc & standard tools) ─────────────────

def read_cpu_times() -> Optional[Tuple[int, int]]:
    """Read (total, idle) jiffies from /proc/stat."""
    try:
        with open("/proc/stat", "r", encoding="utf-8") as fh:
            first = fh.readline().split()
        if not first or first[0] != "cpu":
            return None
        nums = [int(x) for x in first[1:]]
        idle = nums[3] + (nums[4] if len(nums) > 4 else 0)
        total = sum(nums)
        return total, idle
    except OSError:
        return None


def get_cpu_usage_percent(sample_sec: float = 0.4) -> float:
    t1 = read_cpu_times()
    if t1 is None:
        return 0.0
    time.sleep(sample_sec)
    t2 = read_cpu_times()
    if t2 is None:
        return 0.0
    total_delta = t2[0] - t1[0]
    idle_delta = t2[1] - t1[1]
    if total_delta <= 0:
        return 0.0
    return round(100.0 * (1.0 - (idle_delta / total_delta)), 1)


def get_load_avg() -> Tuple[float, float, float]:
    try:
        return os.getloadavg()
    except OSError:
        return (0.0, 0.0, 0.0)


def get_cpu_temp_c() -> Optional[float]:
    thermal_base = Path("/sys/class/thermal")
    if not thermal_base.exists():
        return None
    temps = []
    for zone in sorted(thermal_base.glob("thermal_zone*/temp")):
        try:
            raw = int(zone.read_text(encoding="utf-8").strip())
            if raw > 1000:
                temps.append(raw / 1000.0)
            elif 0 < raw < 150:
                temps.append(float(raw))
        except (OSError, ValueError):
            continue
    return round(max(temps), 1) if temps else None


def get_uptime_str() -> str:
    try:
        sec = float(Path("/proc/uptime").read_text(encoding="utf-8").split()[0])
    except (OSError, ValueError, IndexError):
        return "-"
    days = int(sec // 86400)
    hours = int((sec % 86400) // 3600)
    mins = int((sec % 3600) // 60)
    parts = []
    if days:
        parts.append(f"{days} hari")
    if hours:
        parts.append(f"{hours} jam")
    parts.append(f"{mins} menit")
    return ", ".join(parts)


def get_memory_info() -> Dict[str, float]:
    """Parse /proc/meminfo into bytes & percentages."""
    info: Dict[str, int] = {}
    try:
        with open("/proc/meminfo", "r", encoding="utf-8") as fh:
            for line in fh:
                parts = line.split()
                if len(parts) >= 2:
                    key = parts[0].rstrip(":")
                    info[key] = int(parts[1]) * 1024  # kB -> bytes
    except OSError:
        return {
            "ram_total": 0, "ram_used": 0, "ram_avail": 0, "ram_pct": 0.0,
            "swap_total": 0, "swap_used": 0, "swap_free": 0, "swap_pct": 0.0,
        }

    ram_total = info.get("MemTotal", 0)
    ram_avail = info.get("MemAvailable", info.get("MemFree", 0))
    ram_used = max(0, ram_total - ram_avail)
    ram_pct = round((ram_used / ram_total) * 100.0, 1) if ram_total > 0 else 0.0

    swap_total = info.get("SwapTotal", 0)
    swap_free = info.get("SwapFree", 0)
    swap_used = max(0, swap_total - swap_free)
    swap_pct = round((swap_used / swap_total) * 100.0, 1) if swap_total > 0 else 0.0

    return {
        "ram_total": float(ram_total),
        "ram_used": float(ram_used),
        "ram_avail": float(ram_avail),
        "ram_pct": ram_pct,
        "swap_total": float(swap_total),
        "swap_used": float(swap_used),
        "swap_free": float(swap_free),
        "swap_pct": swap_pct,
    }


def get_disks_info() -> List[Dict[str, object]]:
    code, out, _ = run_cmd(
        ["df", "-B1", "-x", "tmpfs", "-x", "devtmpfs", "-x", "squashfs", "-x", "overlay"]
    )
    disks: List[Dict[str, object]] = []
    if code == 0 and out:
        for line in out.splitlines()[1:]:
            parts = line.split()
            if len(parts) < 6:
                continue
            fs, size_s, used_s, avail_s, pct_s, mount = parts[0], parts[1], parts[2], parts[3], parts[4], parts[5]
            if not fs.startswith("/dev/"):
                continue
            try:
                total = int(size_s)
                used = int(used_s)
                avail = int(avail_s)
                pct = float(pct_s.rstrip("%"))
            except ValueError:
                continue
            disks.append({
                "fs": fs,
                "mount": mount,
                "total": total,
                "used": used,
                "avail": avail,
                "pct": pct,
            })
    if not disks:
        usage = shutil.disk_usage("/")
        pct = round((usage.used / usage.total) * 100.0, 1) if usage.total else 0.0
        disks.append({
            "fs": "/",
            "mount": "/",
            "total": usage.total,
            "used": usage.used,
            "avail": usage.free,
            "pct": pct,
        })
    return disks


def get_top_processes(sort_by: str = "-%mem", limit: int = 5) -> List[str]:
    code, out, _ = run_cmd(["ps", "-eo", "pid,comm,%cpu,%mem", f"--sort={sort_by}"])
    if code != 0 or not out:
        return []
    lines = out.splitlines()[1 : limit + 1]
    result = []
    for line in lines:
        parts = line.split()
        if len(parts) >= 4:
            pid, comm, cpu, mem = parts[0], parts[1], parts[2], parts[3]
            result.append(f"<code>{html_escape(comm[:18]):<18}</code> CPU {cpu}% | RAM {mem}%")
    return result


# ── Docker Metrics Collector ─────────────────────────────────────────────────

def get_docker_containers() -> Tuple[Optional[List[Dict[str, str]]], Optional[str]]:
    """Return list of all containers (ps -a) enriched with live stats."""
    code, out, err = run_cmd(
        ["docker", "ps", "-a", "--format", "{{.Names}}\t{{.State}}\t{{.Status}}\t{{.Image}}"],
        timeout=10,
    )
    if code != 0:
        return None, err or "Docker tidak tersedia atau tidak punya akses ke docker.sock."

    containers: List[Dict[str, str]] = []
    for line in out.splitlines():
        if not line.strip():
            continue
        parts = line.split("\t")
        while len(parts) < 4:
            parts.append("")
        containers.append({
            "name": parts[0],
            "state": parts[1],
            "status": parts[2],
            "image": parts[3],
            "cpu": "-",
            "mem": "-",
            "mem_pct": "-",
        })

    # Enrich running containers with CPU & Memory from docker stats
    stats_code, stats_out, _ = run_cmd(
        ["docker", "stats", "--no-stream", "--format", "{{.Name}}\t{{.CPUPerc}}\t{{.MemUsage}}\t{{.MemPerc}}"],
        timeout=15,
    )
    if stats_code == 0 and stats_out:
        stats_map: Dict[str, Tuple[str, str, str]] = {}
        for line in stats_out.splitlines():
            parts = line.split("\t")
            if len(parts) >= 4:
                stats_map[parts[0]] = (parts[1], parts[2], parts[3])
        for c in containers:
            if c["name"] in stats_map:
                c["cpu"], c["mem"], c["mem_pct"] = stats_map[c["name"]]

    return containers, None


def get_container_logs(name: str, tail: int = 20) -> str:
    if not CONTAINER_NAME_RE.match(name):
        return "❌ Nama container tidak valid."
    code, out, err = run_cmd(["docker", "logs", "--tail", str(tail), name], timeout=10)
    combined = "\n".join(x for x in (out, err) if x).strip()
    if code != 0 and not combined:
        return f"❌ Gagal mengambil log <code>{html_escape(name)}</code>."
    if not combined:
        return f"ℹ️ Log container <code>{html_escape(name)}</code> kosong."
    if len(combined) > 3500:
        combined = combined[-3500:]
    return f"📜 <b>Log terakhir ({tail} baris) — {html_escape(name)}</b>\n<pre>{html_escape(combined)}</pre>"


# ── Report Builders ──────────────────────────────────────────────────────────

def build_status_report() -> str:
    cpu_pct = get_cpu_usage_percent()
    load1, load5, load15 = get_load_avg()
    cores = os.cpu_count() or 1
    temp = get_cpu_temp_c()
    mem = get_memory_info()
    disks = get_disks_info()
    root_disk = next((d for d in disks if d["mount"] == "/"), disks[0])
    containers, docker_err = get_docker_containers()

    lines = [
        f"🖥️ <b>Homelab Status — {html_escape(HOST_LABEL)}</b>",
        f"🕒 {datetime.now().strftime('%d/%m/%Y %H:%M:%S')} | ⏱️ Up: {get_uptime_str()}",
        "",
        f"{status_emoji(cpu_pct, 75, CPU_THRESHOLD)} <b>CPU:</b> {cpu_pct}% ({cores} core) | Load: <code>{load1:.2f} {load5:.2f} {load15:.2f}</code>"
        + (f" | 🌡️ {temp}°C" if temp is not None else ""),
        f"<code>[{progress_bar(cpu_pct)}]</code>",
        "",
        f"{status_emoji(mem['ram_pct'], 75, RAM_THRESHOLD)} <b>RAM:</b> {fmt_bytes(mem['ram_used'])} / {fmt_bytes(mem['ram_total'])} ({mem['ram_pct']}%)",
        f"<code>[{progress_bar(mem['ram_pct'])}]</code> (Avail: {fmt_bytes(mem['ram_avail'])})",
    ]

    if mem["swap_total"] > 0:
        lines.append(
            f"{status_emoji(mem['swap_pct'], 70, SWAP_THRESHOLD)} <b>Swap:</b> {fmt_bytes(mem['swap_used'])} / {fmt_bytes(mem['swap_total'])} ({mem['swap_pct']}%)"
        )

    lines.extend([
        "",
        f"{status_emoji(float(root_disk['pct']), 75, DISK_THRESHOLD)} <b>Disk ({html_escape(str(root_disk['mount']))}):</b> {fmt_bytes(float(root_disk['used']))} / {fmt_bytes(float(root_disk['total']))} ({root_disk['pct']}%)",
        f"<code>[{progress_bar(float(root_disk['pct']))}]</code> (Free: {fmt_bytes(float(root_disk['avail']))})",
        "",
    ])

    if docker_err:
        lines.append(f"🐳 <b>Docker:</b> ⚠️ {html_escape(docker_err)}")
    elif containers is not None:
        running = sum(1 for c in containers if c["state"] == "running")
        stopped = len(containers) - running
        unhealthy = sum(1 for c in containers if "unhealthy" in c["status"].lower())
        badge = "🟢" if stopped == 0 and unhealthy == 0 else "🟡"
        lines.append(
            f"{badge} <b>Docker:</b> {running} aktif | {stopped} mati"
            + (f" | {unhealthy} unhealthy" if unhealthy else "")
            + f" (total {len(containers)})"
        )
        lines.append("<i>Ketik /docker untuk detail container, /ram atau /cpu untuk top proses.</i>")

    return "\n".join(lines)


def build_cpu_report() -> str:
    cpu_pct = get_cpu_usage_percent()
    load1, load5, load15 = get_load_avg()
    cores = os.cpu_count() or 1
    temp = get_cpu_temp_c()
    top = get_top_processes(sort_by="-%cpu", limit=5)

    lines = [
        f"⚙️ <b>CPU Monitor — {html_escape(HOST_LABEL)}</b>",
        f"{status_emoji(cpu_pct, 75, CPU_THRESHOLD)} Penggunaan: <b>{cpu_pct}%</b> ({cores} core)",
        f"<code>[{progress_bar(cpu_pct)}]</code>",
        f"Load Average: <code>{load1:.2f}</code> (1m) | <code>{load5:.2f}</code> (5m) | <code>{load15:.2f}</code> (15m)",
    ]
    if temp is not None:
        lines.append(f"🌡️ Suhu CPU: <b>{temp}°C</b>")
    if top:
        lines.append("\n<b>Top 5 Proses CPU:</b>")
        lines.extend(f"• {row}" for row in top)
    return "\n".join(lines)


def build_ram_report() -> str:
    mem = get_memory_info()
    top = get_top_processes(sort_by="-%mem", limit=6)

    lines = [
        f"🧠 <b>RAM &amp; Swap — {html_escape(HOST_LABEL)}</b>",
        f"{status_emoji(mem['ram_pct'], 75, RAM_THRESHOLD)} <b>RAM:</b> {fmt_bytes(mem['ram_used'])} / {fmt_bytes(mem['ram_total'])} (<b>{mem['ram_pct']}%</b>)",
        f"<code>[{progress_bar(mem['ram_pct'])}]</code>",
        f"Tersedia (Available): <b>{fmt_bytes(mem['ram_avail'])}</b>",
        "",
        f"{status_emoji(mem['swap_pct'], 70, SWAP_THRESHOLD)} <b>Swap:</b> {fmt_bytes(mem['swap_used'])} / {fmt_bytes(mem['swap_total'])} (<b>{mem['swap_pct']}%</b>)",
        f"<code>[{progress_bar(mem['swap_pct'])}]</code>",
    ]
    if top:
        lines.append("\n<b>Top 6 Proses Pemakan RAM:</b>")
        lines.extend(f"• {row}" for row in top)
    return "\n".join(lines)


def build_disk_report() -> str:
    disks = get_disks_info()
    lines = [f"💾 <b>Disk Usage — {html_escape(HOST_LABEL)}</b>", ""]
    for d in disks:
        pct = float(d["pct"])
        lines.append(
            f"{status_emoji(pct, 75, DISK_THRESHOLD)} <b>{html_escape(str(d['mount']))}</b> (<code>{html_escape(str(d['fs']))}</code>)"
        )
        lines.append(
            f"<code>[{progress_bar(pct)}]</code> {pct}% — {fmt_bytes(float(d['used']))} / {fmt_bytes(float(d['total']))} (Free: {fmt_bytes(float(d['avail']))})"
        )
    return "\n".join(lines)


def build_docker_report() -> str:
    containers, err = get_docker_containers()
    if err:
        return f"🐳 <b>Docker Monitor</b>\n❌ {html_escape(err)}"
    if not containers:
        return "🐳 <b>Docker Monitor</b>\nTidak ada container terdaftar."

    running = [c for c in containers if c["state"] == "running"]
    stopped = [c for c in containers if c["state"] != "running"]

    lines = [
        f"🐳 <b>Docker Containers — {html_escape(HOST_LABEL)}</b>",
        f"Aktif: <b>{len(running)}</b> | Berhenti: <b>{len(stopped)}</b> | Total: <b>{len(containers)}</b>",
        "",
    ]

    for c in running:
        icon = "🟡" if "unhealthy" in c["status"].lower() else "🟢"
        lines.append(
            f"{icon} <b>{html_escape(c['name'])}</b>\n"
            f"   CPU: <code>{html_escape(c['cpu'])}</code> | RAM: <code>{html_escape(c['mem'])}</code> ({html_escape(c['mem_pct'])})\n"
            f"   <i>{html_escape(c['status'])}</i>"
        )

    if stopped:
        lines.append("\n<b>Container Berhenti:</b>")
        for c in stopped:
            lines.append(f"🔴 <b>{html_escape(c['name'])}</b> — <i>{html_escape(c['status'])}</i>")

    lines.append("\n<i>Tip: ketik <code>/logs nama_container</code> untuk melihat 20 baris log terakhir.</i>")
    return "\n".join(lines)


def build_help_message() -> str:
    return (
        f"🤖 <b>Homelab Monitor Bot — {html_escape(HOST_LABEL)}</b>\n\n"
        "<b>Perintah tersedia:</b>\n"
        "• /status — Ringkasan CPU, RAM, Disk &amp; Docker\n"
        "• /cpu — Detail CPU, Load Average, Suhu &amp; Top Proses\n"
        "• /ram — Detail RAM, Swap &amp; Top Pemakan RAM\n"
        "• /disk — Penggunaan ruang penyimpanan\n"
        "• /docker — Status, CPU &amp; RAM semua container Docker\n"
        "• /logs <code>nama_container</code> — 20 baris log terakhir container\n"
        "• /help — Tampilkan menu ini\n\n"
        f"🔔 <b>Auto-Alert:</b> {'Aktif' if ALERT_ENABLED else 'Nonaktif'} "
        f"(CPU &gt;{CPU_THRESHOLD:.0f}%, RAM &gt;{RAM_THRESHOLD:.0f}%, Swap &gt;{SWAP_THRESHOLD:.0f}%, Disk &gt;{DISK_THRESHOLD:.0f}%, &amp; Container Down)"
    )


# ── Telegram HTTP API Client (IPv4-safe, zero dependencies) ──────────────────

class TelegramClient:
    def __init__(self, token: str):
        self.base_url = f"https://api.telegram.org/bot{token}"

    def _request(self, method: str, payload: Optional[Dict] = None, timeout: int = 35) -> Optional[Dict]:
        url = f"{self.base_url}/{method}"
        data = None
        headers = {}
        if payload is not None:
            data = json.dumps(payload).encode("utf-8")
            headers["Content-Type"] = "application/json"

        req = urllib.request.Request(url, data=data, headers=headers, method="POST" if data else "GET")
        try:
            with urllib.request.urlopen(req, timeout=timeout) as resp:
                return json.loads(resp.read().decode("utf-8"))
        except (urllib.error.URLError, TimeoutError, json.JSONDecodeError, OSError) as exc:
            print(f"[WARN] Telegram API {method} error: {exc}", file=sys.stderr, flush=True)
            return None

    def get_updates(self, offset: int, timeout: int = 25) -> List[Dict]:
        res = self._request("getUpdates", {"offset": offset, "timeout": timeout, "allowed_updates": ["message"]}, timeout=timeout + 10)
        if res and res.get("ok"):
            return res.get("result", [])
        return []

    def send_message(self, chat_id: str, text: str) -> None:
        # Telegram max message length is 4096 chars
        for i in range(0, len(text), 4000):
            chunk = text[i : i + 4000]
            self._request(
                "sendMessage",
                {
                    "chat_id": chat_id,
                    "text": chunk,
                    "parse_mode": "HTML",
                    "disable_web_page_preview": True,
                },
                timeout=15,
            )


# ── Command Dispatcher ───────────────────────────────────────────────────────

def is_authorized(chat_id: str) -> bool:
    return bool(ALLOWED_CHAT_IDS) and str(chat_id) in ALLOWED_CHAT_IDS


def handle_command(text: str) -> Optional[str]:
    parts = text.strip().split()
    if not parts:
        return None
    cmd = parts[0].split("@")[0].lower()

    if cmd in ("/start", "/help"):
        return build_help_message()
    if cmd == "/status":
        return build_status_report()
    if cmd == "/cpu":
        return build_cpu_report()
    if cmd in ("/ram", "/mem", "/memory"):
        return build_ram_report()
    if cmd == "/disk":
        return build_disk_report()
    if cmd in ("/docker", "/containers", "/container"):
        return build_docker_report()
    if cmd == "/logs":
        if len(parts) < 2:
            return "ℹ️ Gunakan: <code>/logs nama_container</code>"
        return get_container_logs(parts[1])
    return None


# ── Background Watchdog (Proactive Alerts) ───────────────────────────────────

class AlertWatchdog:
    def __init__(self, tg: TelegramClient):
        self.tg = tg
        self.last_alert_ts: Dict[str, float] = {}
        self.prev_container_states: Dict[str, str] = {}
        self.high_cpu_streak = 0

    def _can_alert(self, key: str, now: float) -> bool:
        return (now - self.last_alert_ts.get(key, 0.0)) >= ALERT_COOLDOWN_SEC

    def _broadcast(self, key: str, message: str, force: bool = False) -> None:
        now = time.time()
        if not force and not self._can_alert(key, now):
            return
        self.last_alert_ts[key] = now
        for chat_id in ALLOWED_CHAT_IDS:
            self.tg.send_message(chat_id, message)

    def check_once(self) -> None:
        cpu_pct = get_cpu_usage_percent(sample_sec=0.5)
        if cpu_pct >= CPU_THRESHOLD:
            self.high_cpu_streak += 1
            if self.high_cpu_streak >= 2:
                self._broadcast(
                    "cpu",
                    f"🚨 <b>ALERT CPU TINGGI — {html_escape(HOST_LABEL)}</b>\n"
                    f"CPU mencapai <b>{cpu_pct}%</b> (batas {CPU_THRESHOLD:.0f}%).\n"
                    "Ketik /cpu untuk melihat proses penyebabnya.",
                )
        else:
            self.high_cpu_streak = 0

        mem = get_memory_info()
        if mem["ram_pct"] >= RAM_THRESHOLD:
            self._broadcast(
                "ram",
                f"🚨 <b>ALERT RAM TINGGI — {html_escape(HOST_LABEL)}</b>\n"
                f"RAM: <b>{mem['ram_pct']}%</b> ({fmt_bytes(mem['ram_used'])} / {fmt_bytes(mem['ram_total'])}).\n"
                f"Available: {fmt_bytes(mem['ram_avail'])}. Ketik /ram untuk cek top proses.",
            )

        if mem["swap_total"] > 0 and mem["swap_pct"] >= SWAP_THRESHOLD:
            self._broadcast(
                "swap",
                f"⚠️ <b>ALERT SWAP PENUH — {html_escape(HOST_LABEL)}</b>\n"
                f"Swap: <b>{mem['swap_pct']}%</b> ({fmt_bytes(mem['swap_used'])} / {fmt_bytes(mem['swap_total'])}).\n"
                "Server berisiko melambat / hang. Ketik /ram untuk evaluasi.",
            )

        for d in get_disks_info():
            pct = float(d["pct"])
            if pct >= DISK_THRESHOLD:
                mount = str(d["mount"])
                self._broadcast(
                    f"disk:{mount}",
                    f"🚨 <b>ALERT DISK HAMPIR PENUH — {html_escape(HOST_LABEL)}</b>\n"
                    f"Partisi <code>{html_escape(mount)}</code>: <b>{pct}%</b> (Free: {fmt_bytes(float(d['avail']))}).",
                )

        containers, err = get_docker_containers()
        if not err and containers is not None:
            current = {c["name"]: c["state"] for c in containers}
            if self.prev_container_states:
                for name, prev_state in self.prev_container_states.items():
                    cur_state = current.get(name, "removed")
                    if prev_state == "running" and cur_state != "running":
                        self._broadcast(
                            f"container_down:{name}",
                            f"🔴 <b>CONTAINER MATI — {html_escape(HOST_LABEL)}</b>\n"
                            f"Container <code>{html_escape(name)}</code> berubah dari <b>running</b> → <b>{html_escape(cur_state)}</b>.\n"
                            f"Cek log: <code>/logs {html_escape(name)}</code>",
                            force=True,
                        )
                    elif prev_state != "running" and cur_state == "running":
                        self._broadcast(
                            f"container_up:{name}",
                            f"🟢 <b>CONTAINER PULIH — {html_escape(HOST_LABEL)}</b>\n"
                            f"Container <code>{html_escape(name)}</code> kembali <b>running</b>.",
                            force=True,
                        )
            self.prev_container_states = current

    def run_forever(self) -> None:
        while True:
            try:
                self.check_once()
            except Exception as exc:
                print(f"[WARN] Watchdog error: {exc}", file=sys.stderr, flush=True)
            time.sleep(CHECK_INTERVAL_SEC)


# ── Main Entrypoint ──────────────────────────────────────────────────────────

def main() -> None:
    if not BOT_TOKEN:
        print("[ERROR] TELEGRAM_BOT_TOKEN belum diisi di .env", file=sys.stderr)
        sys.exit(1)
    if not ALLOWED_CHAT_IDS:
        print("[ERROR] ALLOWED_CHAT_IDS belum diisi di .env (wajib demi keamanan)", file=sys.stderr)
        sys.exit(1)

    tg = TelegramClient(BOT_TOKEN)
    print(f"[INFO] Homelab Monitor Bot aktif untuk host '{HOST_LABEL}' (whitelist: {len(ALLOWED_CHAT_IDS)} chat ID)", flush=True)

    if ALERT_ENABLED:
        watchdog = AlertWatchdog(tg)
        threading.Thread(target=watchdog.run_forever, daemon=True).start()

    # Kirim pesan online saat bot pertama kali menyala
    for cid in ALLOWED_CHAT_IDS:
        tg.send_message(cid, f"✅ <b>Homelab Monitor Bot aktif</b> di <code>{html_escape(HOST_LABEL)}</code>.\nKetik /status untuk cek kondisi server.")

    offset = 0
    while True:
        updates = tg.get_updates(offset=offset, timeout=25)
        for upd in updates:
            offset = max(offset, int(upd.get("update_id", 0)) + 1)
            msg = upd.get("message") or {}
            chat_id = str((msg.get("chat") or {}).get("id", ""))
            text = (msg.get("text") or "").strip()
            if not chat_id or not text:
                continue
            if not is_authorized(chat_id):
                print(f"[WARN] Unauthorized chat_id ignored: {chat_id}", file=sys.stderr, flush=True)
                continue
            reply = handle_command(text)
            if reply:
                tg.send_message(chat_id, reply)
        if not updates:
            time.sleep(1)


if __name__ == "__main__":
    main()
