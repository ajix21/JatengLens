import unittest
from unittest.mock import patch, MagicMock

import bot


class HomelabMonitorBotTest(unittest.TestCase):
    def test_progress_bar_and_status_emoji(self):
        self.assertEqual(bot.progress_bar(50, length=10), "█████░░░░░")
        self.assertEqual(bot.progress_bar(0, length=10), "░░░░░░░░░░")
        self.assertEqual(bot.progress_bar(100, length=10), "██████████")
        self.assertEqual(bot.status_emoji(30), "🟢")
        self.assertEqual(bot.status_emoji(78), "🟡")
        self.assertEqual(bot.status_emoji(90), "🔴")

    def test_authorization_whitelist(self):
        with patch.object(bot, "ALLOWED_CHAT_IDS", {"7754154315"}):
            self.assertTrue(bot.is_authorized("7754154315"))
            self.assertFalse(bot.is_authorized("999999999"))
            self.assertFalse(bot.is_authorized(""))

    def test_container_logs_rejects_shell_injection(self):
        res = bot.get_container_logs("openwa-api; rm -rf /")
        self.assertIn("tidak valid", res)
        res2 = bot.get_container_logs("../etc/passwd")
        self.assertIn("tidak valid", res2)

    def test_handle_command_help_and_docker(self):
        help_msg = bot.handle_command("/help")
        self.assertIn("/status", help_msg)
        self.assertIn("/docker", help_msg)

        fake_containers = [
            {"name": "openwa-api", "state": "running", "status": "Up 2 days", "image": "openwa", "cpu": "1.2%", "mem": "500MiB / 1GiB", "mem_pct": "50%"},
            {"name": "lapor_pdf", "state": "exited", "status": "Exited (1) 5 minutes ago", "image": "pdf", "cpu": "-", "mem": "-", "mem_pct": "-"},
        ]
        with patch.object(bot, "get_docker_containers", return_value=(fake_containers, None)):
            docker_msg = bot.handle_command("/docker")
            self.assertIn("openwa-api", docker_msg)
            self.assertIn("lapor_pdf", docker_msg)
            self.assertIn("Aktif: <b>1</b> | Berhenti: <b>1</b>", docker_msg)

    def test_watchdog_alerts_on_high_ram_and_container_down(self):
        mock_tg = MagicMock()
        watchdog = bot.AlertWatchdog(mock_tg)
        watchdog.prev_container_states = {"openwa-api": "running", "androlab_db": "running"}

        fake_mem = {
            "ram_total": 3.2 * 1024**3,
            "ram_used": 3.0 * 1024**3,
            "ram_avail": 0.2 * 1024**3,
            "ram_pct": 93.8,
            "swap_total": 3.7 * 1024**3,
            "swap_used": 3.5 * 1024**3,
            "swap_free": 0.2 * 1024**3,
            "swap_pct": 94.6,
        }
        fake_disks = [{"fs": "/dev/sda1", "mount": "/", "total": 100 * 1024**3, "used": 40 * 1024**3, "avail": 60 * 1024**3, "pct": 40.0}]
        fake_containers = [
            {"name": "openwa-api", "state": "exited", "status": "Exited (137)", "image": "openwa", "cpu": "-", "mem": "-", "mem_pct": "-"},
            {"name": "androlab_db", "state": "running", "status": "Up 5 days", "image": "mariadb", "cpu": "0.1%", "mem": "50MiB", "mem_pct": "10%"},
        ]

        with patch.object(bot, "ALLOWED_CHAT_IDS", {"7754154315"}), \
             patch.object(bot, "get_cpu_usage_percent", return_value=25.0), \
             patch.object(bot, "get_memory_info", return_value=fake_mem), \
             patch.object(bot, "get_disks_info", return_value=fake_disks), \
             patch.object(bot, "get_docker_containers", return_value=(fake_containers, None)):
            watchdog.check_once()

        sent_messages = [call.args[1] for call in mock_tg.send_message.call_args_list]
        self.assertTrue(any("ALERT RAM TINGGI" in m for m in sent_messages))
        self.assertTrue(any("ALERT SWAP PENUH" in m for m in sent_messages))
        self.assertTrue(any("CONTAINER MATI" in m and "openwa-api" in m for m in sent_messages))


if __name__ == "__main__":
    unittest.main()
