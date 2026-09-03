import re

with open('app/Http/Controllers/TelemetryController.php', 'r', encoding='utf-8') as f:
    content = f.read()

save_graph_code = """        return response()->json([
            'success' => true,
            'message' => 'System reset completed successfully.',
        ]);
    }

    public function saveGraph(Request $request)
    {
        $request->validate([
            'locations' => 'required|array',
            'adj' => 'required|array',
        ]);

        $graphPath = base_path('graph.json');
        $data = [
            'locations' => $request->locations,
            'adj' => $request->adj,
        ];

        file_put_contents($graphPath, json_encode($data, JSON_PRETTY_PRINT));

        return response()->json([
            'success' => true,
            'message' => 'Graph map data updated and saved successfully!',
            'total_nodes' => count($request->locations),
        ]);
    }
}"""

content = content.replace("""        return response()->json([
            'success' => true,
            'message' => 'System reset completed successfully.',
        ]);
    }
}""", save_graph_code)

with open('app/Http/Controllers/TelemetryController.php', 'w', encoding='utf-8') as f:
    f.write(content)

print("Added saveGraph to TelemetryController.php!")
