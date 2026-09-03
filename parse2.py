from PIL import Image
import math
import json

img = Image.open(r'C:\Users\ryan8\.gemini\antigravity\brain\1cd80f9d-4821-4f2d-af3b-bd8fab7d8206\scratch\Path2.png').convert('RGB')
width, height = img.size
pixels = img.load()

original_rooms = {
    'Kantin (Display Makanan)': {'x': 16.16, 'y': 34.54},
    'Kantin (Masak Makanan)': {'x': 20.92, 'y': 51.84},
    'Kantin (Tempat Makan)': {'x': 14.78, 'y': 61.46},
    'Kamar Mandi 1': {'x': 27.55, 'y': 29.40},
    'Blank Room 1': {'x': 26.44, 'y': 34.53},
    'Kamar Mandi 2': {'x': 36.13, 'y': 28.41},
    'Blank Room 2': {'x': 42.78, 'y': 31.43},
    'Resepsionis': {'x': 34.91, 'y': 43.18},
    'Pintu Masuk': {'x': 36.65, 'y': 51.84},
    'Meeting Room 1': {'x': 43.61, 'y': 48.95},
    'Meeting Room 2': {'x': 52.20, 'y': 57.49},
    'Meeting Room 3': {'x': 55.20, 'y': 57.54},
    'Meeting Room 4': {'x': 61.51, 'y': 57.52},
    'Meeting Room 5': {'x': 67.93, 'y': 57.52},
    'Ruang Kerja Utama': {'x': 59.24, 'y': 51.27},
    'Meeting Room 6': {'x': 73.53, 'y': 30.06},
    'Ruang Direktur': {'x': 83.71, 'y': 27.74},
    'Tangga dan Lift': {'x': 88.19, 'y': 33.58},
    'Kamar Mandi 3': {'x': 90.72, 'y': 37.46},
    'Kamar Mandi 4': {'x': 90.72, 'y': 48.83},
    'Ruang Manajer': {'x': 75.97, 'y': 42.03}
}

red_pixels = []
for y in range(height):
    for x in range(width):
        r, g, b = pixels[x, y]
        if r > 150 and g < 100 and b < 100:
            red_pixels.append((x, y))

clusters = []
for px, py in red_pixels:
    found = False
    for cluster in clusters:
        cx, cy, pts = cluster
        if math.hypot(px - cx, py - cy) < 18:
            pts.append((px, py))
            found = True
            break
    if not found:
        clusters.append([px, py, [(px, py)]])

nodes = []
for i, cluster in enumerate(clusters):
    cx, cy, pts = cluster
    avg_x = sum(p[0] for p in pts) / len(pts)
    avg_y = sum(p[1] for p in pts) / len(pts)
    nodes.append({
        'id': f'Node_{i}',
        'x': round((avg_x / width) * 100, 2),
        'y': round((avg_y / height) * 100, 2),
        'px_x': avg_x,
        'px_y': avg_y,
        'count': len(pts)
    })

nodes = [n for n in nodes if n['count'] > 15]
nodes.sort(key=lambda n: n['x'])
for i, n in enumerate(nodes):
    n['id'] = f'N{i}'

# Map named rooms to closest nodes
for room_name, coords in original_rooms.items():
    closest_node = None
    min_dist = 9999
    for n in nodes:
        dist = math.hypot(n['x'] - coords['x'], n['y'] - coords['y'])
        if dist < min_dist:
            min_dist = dist
            closest_node = n
    if closest_node:
        closest_node['id'] = room_name

adj = {n['id']: [] for n in nodes}
for i in range(len(nodes)):
    for j in range(i+1, len(nodes)):
        n1 = nodes[i]
        n2 = nodes[j]
        dist = math.hypot(n1['px_x'] - n2['px_x'], n1['px_y'] - n2['px_y'])
        dx = abs(n1['px_x'] - n2['px_x'])
        dy = abs(n1['px_y'] - n2['px_y'])
        
        if dx < 15 or dy < 15:
            clear = True
            for k in range(len(nodes)):
                if k == i or k == j: continue
                nk = nodes[k]
                if min(n1['px_x'], n2['px_x']) - 10 <= nk['px_x'] <= max(n1['px_x'], n2['px_x']) + 10:
                    if min(n1['px_y'], n2['px_y']) - 10 <= nk['px_y'] <= max(n1['px_y'], n2['px_y']) + 10:
                        if dx < 15 and abs(nk['px_x'] - n1['px_x']) < 15: clear = False
                        if dy < 15 and abs(nk['px_y'] - n1['px_y']) < 15: clear = False
                        
            if clear and dist < 350:
                adj[n1['id']].append(n2['id'])
                adj[n2['id']].append(n1['id'])

with open('graph.json', 'w') as f:
    json.dump({'locations': nodes, 'adj': adj}, f)
