import re

with open('resources/views/deliveries.blade.php', 'r', encoding='utf-8') as f:
    content = f.read()

# Replace starting location select dropdown iteration
old_start_select = """                        @foreach($locations as $name => $coords)
                        <option value="{{ $name }}">{{ $name }}</option>
                        @endforeach"""

new_dest_select = """                        @foreach($locations as $name => $coords)
                        @if(($coords['is_destination'] ?? false) || !($coords['hidden'] ?? false))
                        <option value="{{ $name }}">{{ $name }} (Lantai {{ $coords['floor'] ?? 1 }})</option>
                        @endif
                        @endforeach"""

content = content.replace(old_start_select, new_dest_select)

with open('resources/views/deliveries.blade.php', 'w', encoding='utf-8') as f:
    f.write(content)

print("Updated deliveries.blade.php location selects filtering!")
