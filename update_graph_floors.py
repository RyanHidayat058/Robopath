import json

with open('graph.json', 'r', encoding='utf-8') as f:
    data = json.load(f)

for node in data['locations']:
    # Check if node explicitly belongs to Floor 2
    node_id = str(node['id'])
    if 'Lantai 2' in node_id or 'Floor 2' in node_id or 'Atas' in node_id or node_id.startswith('F2_'):
        node['floor'] = 2
    else:
        node['floor'] = 1

with open('graph.json', 'w', encoding='utf-8') as f:
    json.dump(data, f, indent=2)

print("Updated graph.json with floor attributes!")
