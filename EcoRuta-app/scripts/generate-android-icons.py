from pathlib import Path

from PIL import Image


source = Path(r"c:\xampp\htdocs\ecoruta\EcoRuta-app\src\assets\EcoRuta Logo.png")
resources = Path(r"c:\xampp\htdocs\ecoruta\EcoRuta-app\android\app\src\main\res")
sizes = {
    "mipmap-mdpi": 48,
    "mipmap-hdpi": 72,
    "mipmap-xhdpi": 96,
    "mipmap-xxhdpi": 144,
    "mipmap-xxxhdpi": 192,
}

logo = Image.open(source).convert("RGBA")
for folder, size in sizes.items():
    destination = resources / folder
    destination.mkdir(parents=True, exist_ok=True)
    icon = logo.resize((size, size), Image.Resampling.LANCZOS)
    icon.save(destination / "ic_launcher.png")
    icon.save(destination / "ic_launcher_round.png")
    foreground = logo.resize((int(size * 0.8), int(size * 0.8)), Image.Resampling.LANCZOS)
    foreground.save(destination / "ic_launcher_foreground.png")
    print(f"Generated {folder}")