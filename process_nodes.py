import json
import math

with open("graph.json", "r") as f:
    d = json.load(f)

nodes = d["locations"]
adj = d["adj"]

def get_node(nid):
    for n in nodes:
        if n["id"] == nid:
            return n
    return None

# 1. Hapus N58
delete_list = ["N58"]

for n_del in delete_list:
    if n_del in adj:
        neighbors = adj[n_del]
        for i in range(len(neighbors)):
            for j in range(i+1, len(neighbors)):
                n1 = neighbors[i]
                n2 = neighbors[j]
                if n2 not in adj[n1]:
                    adj[n1].append(n2)
                if n1 not in adj[n2]:
                    adj[n2].append(n1)

nodes = [n for n in nodes if n["id"] not in delete_list]
adj = {k: [v_elem for v_elem in v if v_elem not in delete_list] for k, v in adj.items() if k not in delete_list}

# 2. Rename N109 -> Pintu Keluar
n109 = get_node("N109")
if n109:
    n109["id"] = "Pintu Keluar"
    adj["Pintu Keluar"] = adj.pop("N109")
    for k, v in adj.items():
        adj[k] = ["Pintu Keluar" if x == "N109" else x for x in v]
    print("Renamed N109 to Pintu Keluar")

# 3. Add nodes around N214
n214 = get_node("N214")
if n214:
    # Add left and right nodes to N214 if needed
    # Find closest node to the left of N214 and right of N214
    left_n = [n for n in nodes if n["x"] < n214["x"] and abs(n["y"] - n214["y"]) < 2]
    right_n = [n for n in nodes if n["x"] > n214["x"] and abs(n["y"] - n214["y"]) < 2]
    
    if left_n:
        left_closest = sorted(left_n, key=lambda n: abs(n["x"] - n214["x"]))[0]
        if abs(left_closest["x"] - n214["x"]) > 2.0:
            new_id = "N311"
            new_x = round((n214["x"] + left_closest["x"]) / 2, 2)
            new_node = {"id": new_id, "x": new_x, "y": n214["y"]}
            nodes.append(new_node)
            adj[new_id] = ["N214", left_closest["id"]]
            adj["N214"].append(new_id)
            adj[left_closest["id"]].append(new_id)
            print(f"Added {new_id} to left of N214")

    if right_n:
        right_closest = sorted(right_n, key=lambda n: abs(n["x"] - n214["x"]))[0]
        if abs(right_closest["x"] - n214["x"]) > 2.0:
            new_id = "N312"
            new_x = round((n214["x"] + right_closest["x"]) / 2, 2)
            new_node = {"id": new_id, "x": new_x, "y": n214["y"]}
            nodes.append(new_node)
            adj[new_id] = ["N214", right_closest["id"]]
            adj["N214"].append(new_id)
            adj[right_closest["id"]].append(new_id)
            print(f"Added {new_id} to right of N214")

# 4. Add nodes around N200
n200 = get_node("N200")
if n200:
    left_n = [n for n in nodes if n["x"] < n200["x"] and abs(n["y"] - n200["y"]) < 2]
    right_n = [n for n in nodes if n["x"] > n200["x"] and abs(n["y"] - n200["y"]) < 2]
    
    if left_n:
        left_closest = sorted(left_n, key=lambda n: abs(n["x"] - n200["x"]))[0]
        if abs(left_closest["x"] - n200["x"]) > 2.0:
            new_id = "N313"
            new_x = round((n200["x"] + left_closest["x"]) / 2, 2)
            new_node = {"id": new_id, "x": new_x, "y": n200["y"]}
            nodes.append(new_node)
            adj[new_id] = ["N200", left_closest["id"]]
            adj["N200"].append(new_id)
            adj[left_closest["id"]].append(new_id)
            print(f"Added {new_id} to left of N200")

    if right_n:
        right_closest = sorted(right_n, key=lambda n: abs(n["x"] - n200["x"]))[0]
        if abs(right_closest["x"] - n200["x"]) > 2.0:
            new_id = "N314"
            new_x = round((n200["x"] + right_closest["x"]) / 2, 2)
            new_node = {"id": new_id, "x": new_x, "y": n200["y"]}
            nodes.append(new_node)
            adj[new_id] = ["N200", right_closest["id"]]
            adj["N200"].append(new_id)
            adj[right_closest["id"]].append(new_id)
            print(f"Added {new_id} to right of N200")

# Save updated graph
with open("graph.json", "w") as f:
    json.dump({"locations": nodes, "adj": adj}, f)

print("Finished processing nodes.")
