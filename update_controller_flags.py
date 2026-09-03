import re

with open('app/Http/Controllers/DashboardController.php', 'r', encoding='utf-8') as f:
    content = f.read()

old_func = """    private function getLocationsData()
    {
        $graphPath = base_path('graph.json');
        if (! file_exists($graphPath)) {
            return [];
        }
        $data = json_decode(file_get_contents($graphPath), true);
        $locations = [];
        foreach ($data['locations'] as $loc) {
            $locations[$loc['id']] = [
                'x' => $loc['x'],
                'y' => $loc['y'],
                'floor' => $loc['floor'] ?? 1,
            ];
        }

        return $locations;
    }"""

new_func = """    private function getLocationsData()
    {
        $graphPath = base_path('graph.json');
        if (! file_exists($graphPath)) {
            return [];
        }
        $data = json_decode(file_get_contents($graphPath), true);
        $locations = [];
        foreach ($data['locations'] as $loc) {
            $locations[$loc['id']] = [
                'x' => $loc['x'],
                'y' => $loc['y'],
                'floor' => $loc['floor'] ?? 1,
                'hidden' => $loc['hidden'] ?? false,
                'is_destination' => $loc['is_destination'] ?? false,
            ];
        }

        return $locations;
    }"""

content = content.replace(old_func, new_func)

with open('app/Http/Controllers/DashboardController.php', 'w', encoding='utf-8') as f:
    f.write(content)

print("Updated DashboardController.php getLocationsData with hidden and is_destination attributes!")
