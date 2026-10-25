# -*- coding: utf-8 -*-
from pathlib import Path

NAME = "Azzeddine ATIBI"
APOS = "\u2019"  # typographic apostrophe (safe inside PHP single-quoted strings)
p = Path(__file__).resolve().parents[1] / "includes" / "i18n.php"
text = p.read_text(encoding="utf-8")

text = text.replace("Professor", NAME)
text = text.replace("professor", NAME)
text = text.replace(f"role.{NAME}", "role.professor")
text = text.replace(
    "Professeur (directeur de laboratoire)",
    f"{NAME} (directeur de laboratoire)",
)
text = text.replace("أستاذ (مدير المختبر)", f"{NAME} (مدير المختبر)")
text = text.replace("أستاذ (مسؤول)", f"{NAME} (مسؤول)")
text = text.replace('Azzeddine ATIBI"', NAME)

# French grammar (typographic apostrophe)
text = text.replace(f"le {NAME}", NAME)
text = text.replace(f"par le {NAME}", f"par {NAME}")
text = text.replace(f"que le {NAME}", f"qu{APOS}{NAME}")
text = text.replace(f"du {NAME}", f"d{APOS}{NAME}")
text = text.replace(f"autour du {NAME}", f"autour d{APOS}{NAME}")
# Fix accidental ASCII apostrophes from earlier runs
text = text.replace(f"d'{NAME}", f"d{APOS}{NAME}")
text = text.replace(f"qu'{NAME}", f"qu{APOS}{NAME}")

p.write_text(text, encoding="utf-8")
print("Updated", p)
