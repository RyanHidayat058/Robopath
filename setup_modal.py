import re

sidebar_nav = """                <a href="{{ route('history') }}" 
                   class="flex items-center gap-4 px-4 py-3 rounded transition duration-200 group {{ Route::is('history') ? 'bg-white text-brand-blue font-semibold shadow-sm' : 'text-white/80 hover:bg-white/10 hover:text-white' }}">
                    <i class="fa-solid fa-clock-rotate-left text-lg {{ Route::is('history') ? 'text-brand-blue' : 'text-white/70 group-hover:text-white' }}"></i>
                    <span class="text-sm">History</span>
                </a>

                <!-- BOT CONTROL NAV BUTTON -->
                <button type="button" onclick="openBotControlModal()" 
                        class="w-full flex items-center gap-4 px-4 py-3 rounded transition duration-200 text-white/80 hover:bg-white/10 hover:text-white group">
                    <i class="fa-solid fa-sliders text-lg text-white/70 group-hover:text-white"></i>
                    <span class="text-sm">Bot Control</span>
                </button>"""

with open('resources/views/layouts/layout.blade.php', 'r', encoding='utf-8') as f:
    content = f.read()

# 1. Remove the ugly sidebar cards
start_idx = content.find('<!-- GLOBAL BOT CONTROL CENTER -->')
end_idx = content.find('<!-- Sidebar Footer / System Health -->')
if start_idx != -1 and end_idx != -1:
    content = content[:start_idx] + content[end_idx:]

# 2. Replace History nav item to include Bot Control button
content = re.sub(r'<a href="{{ route\(\'history\'\) }}".*?</a>', sidebar_nav, content, flags=re.DOTALL)

# 3. Add Modal HTML before </body>
modal_html = """
    <!-- BOT CONTROL MODAL -->
    <div id="bot-control-modal" class="fixed inset-0 bg-slate-900/60 backdrop-blur-sm z-50 flex items-center justify-center hidden p-4">
        <div class="bg-white rounded-2xl shadow-2xl border border-gray-100 w-full max-w-xl overflow-hidden animate-in fade-in zoom-in-95 duration-200">
            <!-- Modal Header -->
            <div class="px-6 py-4 bg-[#3b4cb8] text-white flex justify-between items-center">
                <div class="flex items-center gap-3">
                    <div class="w-8 h-8 rounded-lg bg-white/20 flex items-center justify-center text-white">
                        <i class="fa-solid fa-sliders"></i>
                    </div>
                    <div>
                        <h3 class="font-bold text-base">Bot Control Center</h3>
                        <p class="text-xs text-white/80">Manage robot battery levels and operational states</p>
                    </div>
                </div>
                <button onclick="closeBotControlModal()" class="w-8 h-8 rounded-lg bg-white/10 hover:bg-white/20 text-white flex items-center justify-center transition duration-200">
                    <i class="fa-solid fa-xmark"></i>
                </button>
            </div>

            <!-- Modal Body -->
            <div class="p-6 space-y-4 max-h-[70vh] overflow-y-auto custom-scrollbar">
                @php
                    $globalRobots = \App\Models\Robot::all();
                @endphp
                @if(isset($globalRobots) && count($globalRobots) > 0)
                    @foreach($globalRobots as $bot)
                        @php
                            $isMaint = $bot->status === 'Maintenance';
                        @endphp
                        <div class="bg-slate-50 border border-slate-200 rounded-xl p-4 flex flex-col md:flex-row md:items-center justify-between gap-4 hover:border-[#3b4cb8]/40 transition duration-200">
                            <div class="flex items-center gap-3">
                                <div class="w-10 h-10 rounded-xl bg-[#3b4cb8]/10 text-[#3b4cb8] flex items-center justify-center text-lg border border-[#3b4cb8]/20">
                                    <i class="fa-solid fa-robot"></i>
                                </div>
                                <div>
                                    <div class="flex items-center gap-2">
                                        <h4 class="font-bold text-slate-800 text-sm">{{ $bot->name }}</h4>
                                        <span class="text-[10px] font-bold px-2 py-0.5 rounded-full {{ $isMaint ? 'bg-amber-100 text-amber-700 border border-amber-300' : ($bot->status === 'Delivering' ? 'bg-blue-100 text-blue-700 border border-blue-300' : 'bg-emerald-100 text-emerald-700 border border-emerald-300') }}">
                                            {{ $bot->status }}
                                        </span>
                                    </div>
                                    <p class="text-xs text-slate-500 mt-0.5">Location: <span class="font-medium text-slate-700">({{ $bot->current_x }}%, {{ $bot->current_y }}%)</span></p>
                                </div>
                            </div>

                            <div class="flex items-center gap-4">
                                <!-- Battery Indicator -->
                                <div class="w-32">
                                    <div class="flex justify-between text-xs mb-1">
                                        <span class="text-slate-500 text-[11px] font-semibold"><i class="fa-solid fa-battery-three-quarters text-[#3b4cb8]"></i> Battery</span>
                                        <span class="font-bold text-slate-700 text-[11px]">{{ $bot->battery_level }}%</span>
                                    </div>
                                    <div class="w-full bg-slate-200 rounded-full h-2 overflow-hidden">
                                        <div class="h-2 rounded-full {{ $bot->battery_level > 20 ? 'bg-emerald-500' : 'bg-rose-500' }}" style="width: {{ $bot->battery_level }}%"></div>
                                    </div>
                                </div>

                                <!-- Actions -->
                                <div class="flex items-center gap-2">
                                    <button onclick="globalUpdateBotSettings({{ $bot->id }}, 'battery_level', 100)" class="px-3 py-1.5 bg-white border border-slate-300 hover:border-[#3b4cb8] text-[#3b4cb8] rounded-lg text-xs font-bold shadow-sm transition duration-200 flex items-center gap-1.5">
                                        <i class="fa-solid fa-bolt text-amber-500"></i> Charge
                                    </button>
                                    <button onclick="globalUpdateBotSettings({{ $bot->id }}, 'status', '{{ $isMaint ? 'Idle' : 'Maintenance' }}')" class="px-3 py-1.5 bg-white border {{ $isMaint ? 'border-emerald-300 text-emerald-600 hover:bg-emerald-50' : 'border-rose-300 text-rose-600 hover:bg-rose-50' }} rounded-lg text-xs font-bold shadow-sm transition duration-200 flex items-center gap-1.5">
                                        <i class="fa-solid {{ $isMaint ? 'fa-play' : 'fa-wrench' }}"></i> {{ $isMaint ? 'Set Idle' : 'Maintenance' }}
                                    </button>
                                </div>
                            </div>
                        </div>
                    @endforeach
                @endif
            </div>

            <!-- Modal Footer -->
            <div class="px-6 py-3 bg-slate-100 border-t border-slate-200 flex justify-end">
                <button onclick="closeBotControlModal()" class="px-4 py-2 bg-slate-200 hover:bg-slate-300 text-slate-700 rounded-lg text-xs font-bold transition duration-200">
                    Close
                </button>
            </div>
        </div>
    </div>

    <script>
        function openBotControlModal() {
            document.getElementById('bot-control-modal').classList.remove('hidden');
        }
        function closeBotControlModal() {
            document.getElementById('bot-control-modal').classList.add('hidden');
        }
    </script>
"""

content = content.replace('</body>', modal_html + '\n</body>')

with open('resources/views/layouts/layout.blade.php', 'w', encoding='utf-8') as f:
    f.write(content)

print("SUCCESS")
