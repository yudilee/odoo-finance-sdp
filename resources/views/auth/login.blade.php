<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login - Cash Bank</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="bg-slate-900 min-h-screen flex items-center justify-center p-4">
    <div class="w-full max-w-md">
        {{-- Logo --}}
        <div class="text-center mb-8">
            <h1 class="text-4xl font-bold bg-gradient-to-r from-emerald-400 to-cyan-400 bg-clip-text text-transparent">Cash Bank</h1>
            <p class="text-slate-400 mt-2">Sign in to your account</p>
        </div>

        {{-- Login Card --}}
        <div class="bg-slate-800 rounded-2xl p-8 shadow-xl border border-slate-700">
            @if($errors->any())
                <div class="mb-6 bg-red-900/30 border border-red-700 text-red-300 p-4 rounded-lg text-sm">
                    @foreach($errors->all() as $error)
                        <p>{{ $error }}</p>
                    @endforeach
                </div>
            @endif

            @if(session('warning'))
                <div class="mb-6 bg-amber-900/30 border border-amber-700 text-amber-300 p-4 rounded-lg text-sm">
                    {{ session('warning') }}
                </div>
            @endif

            <form method="POST" action="{{ route('login') }}">
                @csrf
                <div class="space-y-5">
                    <div>
                        <label for="email" class="block text-sm font-medium text-slate-300 mb-2">Email</label>
                        <input type="text" name="email" id="email" value="{{ old('email') }}" required autofocus
                            class="w-full px-4 py-3 bg-slate-900 border border-slate-600 rounded-lg text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition">
                    </div>
                    <div>
                        <label for="password" class="block text-sm font-medium text-slate-300 mb-2">Password</label>
                        <input type="password" name="password" id="password" required
                            class="w-full px-4 py-3 bg-slate-900 border border-slate-600 rounded-lg text-white placeholder-slate-500 focus:outline-none focus:border-emerald-500 focus:ring-1 focus:ring-emerald-500 transition">
                    </div>
                    <div class="flex items-center">
                        <input type="checkbox" name="remember" id="remember" class="w-4 h-4 text-emerald-500 bg-slate-900 border-slate-600 rounded focus:ring-emerald-500">
                        <label for="remember" class="ml-2 text-sm text-slate-400">Remember me</label>
                    </div>
                    <button type="submit" class="w-full py-3 bg-gradient-to-r from-emerald-500 to-cyan-500 text-white font-semibold rounded-lg hover:from-emerald-600 hover:to-cyan-600 transition-all shadow-lg shadow-emerald-500/25">
                        Sign In
                    </button>
                </div>
            </form>
        </div>

        <p class="text-center text-slate-500 text-sm mt-6">
            Don't have an account? <a href="{{ route('register') }}" class="text-emerald-400 hover:text-emerald-300 transition">Register</a>
        </p>
    </div>
</body>
</html>
