
import re

with open("resources/views/dashboard.blade.php", "r") as f:
    content = f.read()

# Replace \${ with ${
content = content.replace("\\${", "${")
content = content.replace("\\`", "`")

with open("resources/views/dashboard.blade.php", "w") as f:
    f.write(content)

