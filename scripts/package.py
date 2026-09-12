"""Build a distributable archive without development files or local data."""
from pathlib import Path
from zipfile import ZipFile, ZIP_DEFLATED
root = Path(__file__).resolve().parent.parent
output = root / 'dist' / 'fluent-booking-addon-4.0.0-alpha.1.zip'
output.parent.mkdir(exist_ok=True)
files = [root / name for name in ['wk-fluent-multireservation.php', 'autoload.php', 'README.md', 'CHANGELOG.md']]
files += sorted((root / 'app').rglob('*.php'))
files += sorted((root / 'docs').glob('*.md'))
with ZipFile(output, 'w', ZIP_DEFLATED) as archive:
    for path in files:
        archive.write(path, 'fluent-booking-addon/' + str(path.relative_to(root)))
with ZipFile(output) as archive:
    assert archive.testzip() is None
    assert all(name.startswith('fluent-booking-addon/') for name in archive.namelist())
print(output)
