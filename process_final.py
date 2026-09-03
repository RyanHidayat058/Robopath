import json

with open("graph.json", "r") as f:
    d = json.load(f)

nodes = d["locations"]
adj = d["adj"]

def get_node(nid):
    for n in nodes:
        if n["id"] == nid: return n
    return None

# 1. Delete N55
delete_list = ["N55"]
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

# 2. Add node to the right of N160
n160 = get_node("N160")
if n160:
    # Find right neighbors or closest node to the right of N160 at roughly same Y
    right_nodes = [n for n in nodes if n["x"] > n160["x"] and abs(n["y"] - n160["y"]) < 4]
    if right_nodes:
        right_closest = sorted(right_nodes, key=lambda n: abs(n["x"] - n160["x"]))[0]
        new_id = "N315"
        new_x = round((n160["x"] + right_closest["x"]) / 2, 2)
        new_node = {"id": new_id, "x": new_x, "y": n160["y"]}
        nodes.append(new_node)
        adj[new_id] = ["N160", right_closest["id"]]
        adj["N160"].append(new_id)
        adj[right_closest["id"]].append(new_id)
        print(f"Added {new_id} to right of N160")

with open("graph.json", "w") as f:
    json.dump({"locations": nodes, "adj": adj}, f)

print("Finished N55 and N160 updates.")
