import re

with open('resources/views/deliveries.blade.php', 'r', encoding='utf-8') as f:
    content = f.read()

# Add floor switcher to deliveries live map header
old_header = """            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-base font-bold text-gray-800">Live Active Tracking</h3>
                    <p class="text-xs text-gray-500">Smooth navigation and real-time path visualization</p>
                </div>
            </div>"""

new_header = """            <div class="flex items-center justify-between mb-4">
                <div>
                    <h3 class="text-base font-bold text-gray-800">Live Active Tracking</h3>
                    <p class="text-xs text-gray-500">Smooth navigation and real-time multi-floor path visualization</p>
                </div>
                <div class="flex items-center gap-1 bg-gray-100 p-1 rounded-xl border border-gray-200 text-xs font-bold">
                    <button onclick="switchLiveFloor(1)" id="btn-deliv-f1" class="px-3 py-1.5 rounded-lg bg-[#3b4cb8] text-white shadow transition">
                        Lantai 1
                    </button>
                    <button onclick="switchLiveFloor(2)" id="btn-deliv-f2" class="px-3 py-1.5 rounded-lg text-gray-600 hover:text-gray-900 transition">
                        Lantai 2
                    </button>
                </div>
            </div>"""

content = content.replace(old_header, new_header)

# Add switchLiveFloor function
floor_func = """    let currentLiveFloor = 1;
    function switchLiveFloor(f) {
        currentLiveFloor = f;
        document.getElementById('btn-deliv-f1').className = f === 1 ? 'px-3 py-1.5 rounded-lg bg-[#3b4cb8] text-white shadow transition font-bold' : 'px-3 py-1.5 rounded-lg text-gray-600 hover:text-gray-900 transition font-bold';
        document.getElementById('btn-deliv-f2').className = f === 2 ? 'px-3 py-1.5 rounded-lg bg-[#3b4cb8] text-white shadow transition font-bold' : 'px-3 py-1.5 rounded-lg text-gray-600 hover:text-gray-900 transition font-bold';
        document.getElementById('map-container').style.backgroundImage = `url('${f === 1 ? "{{ asset('images/floor1.jpeg') }}" : "{{ asset('images/floor2.jpeg') }}"}')`;
    }"""

content = content.replace("let activeDeliveries = @json($activeDeliveries);", "let activeDeliveries = @json($activeDeliveries);\n" + floor_func)

with open('resources/views/deliveries.blade.php', 'w', encoding='utf-8') as f:
    f.write(content)

print("Updated deliveries.blade.php with live floor switcher!")
