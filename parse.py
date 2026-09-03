from PIL import Image
import math

img = Image.open(r'C:\Users\ryan8\.gemini\antigravity\brain\1cd80f9d-4821-4f2d-af3b-bd8fab7d8206\scratch\Path2.png').convert('RGB')
width, height = img.size
pixels = img.load()

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

print("LOCATIONS:")
for n in nodes:
    print(f"'{n['id']}': {{ x: {n['x']}, y: {n['y']} }}, // count: {n['count']}")

print("\nADJACENCY:")
for n_id, neighbors in adj.items():
    print(f"'{n_id}': {neighbors},")
