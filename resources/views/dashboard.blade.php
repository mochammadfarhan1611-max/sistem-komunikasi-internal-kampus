<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8" />
    <meta name="viewport" content="width=device-width, initial-scale=1.0" />
    <meta name="csrf-token" content="{{ csrf_token() }}" />
    <title>{{ isset($receiver) ? $receiver->nama_lengkap.' — SKIK' : 'Dashboard Komunikasi — SKIK' }}</title>

    <link rel="preconnect" href="https://fonts.googleapis.com" />
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin />
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;700&display=swap" rel="stylesheet" />
    <link href="https://fonts.googleapis.com/css2?family=Material+Symbols+Outlined:opsz,wght,FILL,GRAD@20..48,400,0,0" rel="stylesheet" />

    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        body {
            font-family: 'Inter', ui-sans-serif, system-ui, sans-serif;
            overscroll-behavior: none;
        }
        .no-scrollbar::-webkit-scrollbar {
            display: none;
        }
        .no-scrollbar {
            -ms-overflow-style: none;
            scrollbar-width: none;
        }
    </style>
</head>
<body class="h-screen overflow-hidden bg-slate-100 text-slate-800 antialiased selection:bg-blue-100 selection:text-blue-900">
    <div class="relative flex h-full w-full overflow-hidden bg-white">
        <div
            id="sidebar-backdrop"
            class="fixed inset-0 z-30 hidden bg-slate-900/40 backdrop-blur-[1px] md:hidden"
        ></div>

        <x-chat-sidebar
            :kontak="$kontak"
            :receiver="$receiver ?? null"
            :tahun-aktif="$tahunAktif ?? null"
        />

        <x-chat-area
            :receiver="$receiver ?? null"
            :messages="$messages ?? collect()"
        />
    </div>

    <script src="{{ asset('js/chat.js') }}" defer></script>
</body>
</html>
