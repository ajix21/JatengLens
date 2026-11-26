<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — SocioWatch Jateng</title>
    <link rel="stylesheet" href="{{ asset('vendor/tailwind.min.css') }}">
    <link rel="stylesheet" href="{{ asset('vendor/fontawesome/css/all.min.css') }}">
    <style>
        body { background: #0a0f1e; }
        .card-glow { box-shadow: 0 0 60px rgba(79,70,229,.2), 0 25px 50px rgba(0,0,0,.5); }
        .input-dark {
            background: rgba(255,255,255,.05);
            border: 1px solid rgba(255,255,255,.1);
            color: #e2e8f0;
            transition: border-color .2s, box-shadow .2s;
        }
        .input-dark:focus {
            outline: none;
            border-color: #4F46E5;
            box-shadow: 0 0 0 3px rgba(79,70,229,.25);
        }
        .input-dark::placeholder { color: #64748b; }
        .btn-login {
            background: linear-gradient(135deg,#4F46E5,#7C3AED);
            transition: opacity .2s, box-shadow .2s;
        }
        .btn-login:hover { opacity:.9; box-shadow: 0 0 24px rgba(79,70,229,.55); }
        .bg-grid {
            background-image: linear-gradient(rgba(255,255,255,.03) 1px, transparent 1px),
                              linear-gradient(90deg, rgba(255,255,255,.03) 1px, transparent 1px);
            background-size: 40px 40px;
        }
    </style>
</head>
<body class="min-h-screen flex items-center justify-center bg-grid">

    {{-- Decorative orbs --}}
    <div class="fixed top-0 left-1/4 w-96 h-96 rounded-full pointer-events-none"
         style="background:radial-gradient(circle,rgba(79,70,229,.15),transparent 70%);"></div>
    <div class="fixed bottom-0 right-1/4 w-96 h-96 rounded-full pointer-events-none"
         style="background:radial-gradient(circle,rgba(124,58,237,.12),transparent 70%);"></div>

    <div class="relative z-10 w-full max-w-md px-4">

        {{-- Card --}}
        <div class="rounded-2xl p-8 card-glow"
             style="background:linear-gradient(135deg,#0d1424,#111827); border:1px solid rgba(255,255,255,.08);">

            {{-- Logo --}}
            <div class="flex flex-col items-center mb-8">
                <div class="w-16 h-16 rounded-2xl flex items-center justify-center mb-4"
                     style="background:linear-gradient(135deg,#4F46E5,#7C3AED); box-shadow:0 0 30px rgba(79,70,229,.5);">
                    <i class="fas fa-satellite-dish text-white text-2xl"></i>
                </div>
                <h1 class="text-white font-bold text-2xl tracking-tight">SocioWatch</h1>
                <p class="text-xs font-semibold tracking-widest mt-1" style="color:#818CF8">JAWA TENGAH</p>
                <p class="text-slate-400 text-sm mt-3">Sistem Pemantauan Akun Media Sosial</p>
            </div>

            {{-- Error --}}
            @if ($errors->any())
            <div role="alert" class="mb-5 px-4 py-3 rounded-xl text-sm" style="background:rgba(239,68,68,.1); border:1px solid rgba(239,68,68,.3); color:#fca5a5;">
                <i class="fas fa-exclamation-circle mr-2"></i>{{ $errors->first() }}
            </div>
            @endif

            {{-- Form --}}
            <form method="POST" action="{{ route('login') }}" onsubmit="const b=this.querySelector('button[type=submit]'); b.disabled=true; b.style.opacity='0.7';" class="space-y-5">
                @csrf

                <div>
                    <label for="email" class="block text-sm font-medium text-slate-300 mb-1.5">Email</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500">
                            <i class="fas fa-envelope text-sm"></i>
                        </span>
                        <input id="email" type="email" name="email" value="{{ old('email') }}" required autofocus autocomplete="username"
                               class="input-dark w-full rounded-xl pl-10 pr-4 py-3 text-sm"
                               placeholder="email@sociowatch.id">
                    </div>
                </div>

                <div>
                    <label for="password" class="block text-sm font-medium text-slate-300 mb-1.5">Password</label>
                    <div class="relative">
                        <span class="absolute left-3 top-1/2 -translate-y-1/2 text-slate-500">
                            <i class="fas fa-lock text-sm"></i>
                        </span>
                        <input id="password" type="password" name="password" required autocomplete="current-password"
                               class="input-dark w-full rounded-xl pl-10 pr-10 py-3 text-sm"
                               placeholder="••••••••">
                        <button type="button" aria-label="Tampilkan atau sembunyikan password"
                                onclick="const p=document.getElementById('password'); const i=this.querySelector('i'); if(p.type==='password'){p.type='text';i.className='fas fa-eye-slash text-xs';}else{p.type='password';i.className='fas fa-eye text-xs';}"
                                class="absolute right-3 top-1/2 -translate-y-1/2 text-slate-400 hover:text-slate-200">
                            <i class="fas fa-eye text-xs"></i>
                        </button>
                    </div>
                </div>

                <div class="flex items-center justify-between">
                    <label class="flex items-center gap-2 cursor-pointer">
                        <input type="checkbox" name="remember" class="rounded" style="accent-color:#4F46E5">
                        <span class="text-sm text-slate-400">Ingat saya</span>
                    </label>
                </div>

                <button type="submit" class="btn-login w-full py-3 rounded-xl text-white font-semibold text-sm">
                    <i class="fas fa-sign-in-alt mr-2"></i>Masuk ke Sistem
                </button>
            </form>

            {{-- Footer --}}
            <p class="text-center text-xs text-slate-400 mt-8">
                SocioWatch Jateng &copy; {{ date('Y') }} · Dinas Komunikasi & Informatika
            </p>
        </div>
    </div>

</body>
</html>
