import json

with open('graph.json', 'r', encoding='utf-8') as f:
    data = json.load(f)

for node in data['locations']:
    node_id = str(node['id'])
    # If the node ID does not start with 'N' followed by digits (or is a known room name), mark as named destination
    is_transit = node_id.startswith('N') and node_id[1:].isdigit()
    
    node['floor'] = node.get('floor', 1)
    node['hidden'] = is_transit  # Hide intermediate transit dots by default on clean map view
    node['is_destination'] = not is_transit  # Named room destinations available in pickup/dropoff dropdowns

with open('graph.json', 'w', encoding='utf-8') as f:
    json.dump(data, f, indent=2)

print("Updated graph.json with hidden and is_destination flags!")
