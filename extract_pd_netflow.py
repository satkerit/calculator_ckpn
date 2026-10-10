import openpyxl
import json

wb = openpyxl.load_workbook('docs/Kertas kerja.xlsx', data_only=True)
sheets = wb.sheetnames
print(f"Available sheets: {sheets}\n")

# Find PD Netflow sheet
target = None
for s in sheets:
    if 'netflow' in s.lower() or 'pd' in s.lower():
        target = s
        break

if not target:
    print("Sheet 'PD Netflow' tidak ditemukan. Gunakan sheet pertama.")
    target = sheets[0]

ws = wb[target]
print(f"Reading sheet: {target}")
print(f"Dimensions: {ws.dimensions}\n")

# Read first 50 rows untuk capture 3 tabel
print("=" * 100)
for i, row in enumerate(ws.iter_rows(min_row=1, max_row=50, values_only=True), 1):
    # Format row dengan padding
    cells = [str(c) if c is not None else '' for c in row[:20]]  # Max 20 kolom
    print(f"Row {i:2d}: {' | '.join(cells[:10])}")

wb.close()
