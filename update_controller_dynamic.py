import re

with open('app/Http/Controllers/DashboardController.php', 'r', encoding='utf-8') as f:
    content = f.read()

# Replace getLocationsData and getAdjData methods with dynamic graph.json readers
old_methods = re.search(r'private function getLocationsData\(\).*', content, re.DOTALL).group(0)

new_methods = """private function getLocationsData()
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
    }

    private function getAdjData()
    {
        $graphPath = base_path('graph.json');
        if (! file_exists($graphPath)) {
            return [];
        }
        $data = json_decode(file_get_contents($graphPath), true);

        return $data['adj'] ?? [];
    }
}"""

content = content[:content.find('private function getLocationsData()')] + new_methods

with open('app/Http/Controllers/DashboardController.php', 'w', encoding='utf-8') as f:
    f.write(content)

print("Updated DashboardController.php to read graph.json dynamically!")
