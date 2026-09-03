import re

with open('resources/views/layouts/layout.blade.php', 'r', encoding='utf-8') as f:
    content = f.read()

# Remove old modal if present
start_idx = content.find('<!-- BOT CONTROL MODAL -->')
if start_idx != -1:
    end_script = content.find('</script>', start_idx) + 9
    content = content[:start_idx] + content[end_script:]

# Navigation link HTML for Bot Control
bot_nav = """                <a href="{{ route('bot-control') }}" 
                   class="flex items-center gap-4 px-4 py-3 rounded transition duration-200 group {{ Route::is('bot-control') ? 'bg-white text-brand-blue font-semibold shadow-sm' : 'text-white/80 hover:bg-white/10 hover:text-white' }}">
                    <i class="fa-solid fa-sliders text-lg {{ Route::is('bot-control') ? 'text-brand-blue' : 'text-white/70 group-hover:text-white' }}"></i>
                    <span class="text-sm">Bot Control</span>
                </a>
"""

# Replace BOT CONTROL NAV BUTTON or place after Deliveries
if '<!-- BOT CONTROL NAV BUTTON -->' in content:
    content = re.sub(r'<!-- BOT CONTROL NAV BUTTON -->.*?<\/button>', bot_nav.strip(), content, flags=re.DOTALL)
else:
    content = content.replace('href="{{ route(\'deliveries\') }}"', 'href="{{ route(\'deliveries\') }}"')
    # insert right after deliveries </a>
    deliv_pos = content.find('href="{{ route(\'deliveries\') }}"')
    end_a = content.find('</a>', deliv_pos) + 4
    content = content[:end_a] + '\n\n' + bot_nav.strip() + content[end_a:]

with open('resources/views/layouts/layout.blade.php', 'w', encoding='utf-8') as f:
    f.write(content)

print("Updated layout.blade.php cleanly")
