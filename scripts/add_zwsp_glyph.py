"""
เพิ่ม glyph ความกว้างศูนย์สำหรับ U+200B (ZERO WIDTH SPACE) ลงในไฟล์ฟอนต์ .ttf

ทำไมต้องทำ:
  mPDF (useDictionaryLBR) ตัดคำภาษาไทยโดยแทรก U+200B ระหว่างคำ
  ถ้าฟอนต์ไม่มี glyph ของ U+200B (TH Sarabun New ไม่มี) จะแสดงเป็นกล่องสี่เหลี่ยม

วิธีใช้:
  pip install fonttools
  python scripts/add_zwsp_glyph.py resources/fonts/*.ttf
  # แล้วลบ cache ฟอนต์ของ mPDF:  rm storage/app/mpdf/*

ไฟล์ต้นฉบับจะถูกสำรองเป็น <ชื่อไฟล์>.orig ก่อนเขียนทับ (ถ้ายังไม่มี)
"""

import shutil
import sys
from pathlib import Path

from fontTools.ttLib import TTFont
from fontTools.ttLib.tables._g_l_y_f import Glyph

ZWSP = 0x200B
GLYPH_NAME = "zerowidthspace"


def patch(path: Path) -> None:
    font = TTFont(path)
    cmap = font.getBestCmap()

    if ZWSP in cmap:
        print(f"skip   {path.name}: U+200B already mapped to {cmap[ZWSP]}")
        return

    backup = path.with_suffix(path.suffix + ".orig")
    if not backup.exists():
        shutil.copy2(path, backup)

    glyph_order = font.getGlyphOrder()
    name = GLYPH_NAME
    while name in glyph_order:
        name += "_"

    # 1) glyph ว่าง (ไม่มี contour)
    font["glyf"].glyphs[name] = Glyph()
    # 2) advance width = 0, lsb = 0
    font["hmtx"].metrics[name] = (0, 0)
    if "vmtx" in font:
        font["vmtx"].metrics[name] = (0, 0)
    # 3) เพิ่มใน glyph order
    font.setGlyphOrder(glyph_order + [name])
    font["maxp"].numGlyphs = len(glyph_order) + 1
    # 4) map U+200B ในทุก unicode cmap subtable
    for table in font["cmap"].tables:
        if table.isUnicode():
            table.cmap[ZWSP] = name

    font.save(path)
    print(f"patched {path.name}: U+200B -> {name}")


if __name__ == "__main__":
    files = [Path(p) for p in sys.argv[1:]]
    if not files:
        print(__doc__)
        sys.exit(1)
    for f in files:
        patch(f)
