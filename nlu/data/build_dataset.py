"""Build the training set for the intent model.

Three sources, kept apart on purpose:
  1. templates.py   phrasings x fillers (days, times, services) in darija, French, Arabic
  2. augment()      the spelling noise real people type: 3/a, 9/q, 7/h, ou/o/u, dropped
                    vowels, doubled letters, missing spaces
  3. corrections.jsonl  exported from the dashboard (php artisan nlu:export): intents the
                    clinic corrected and the FAQ variants it added. Training only.
  4. handwritten_test.jsonl   written by hand and NEVER generated or augmented. It is the
                    only honest measure of how the model does on messages it has not seen.

Usage: python data/build_dataset.py  ->  data/train.jsonl, data/val.jsonl
"""
from __future__ import annotations

import json
import random
import sys
from pathlib import Path

sys.path.insert(0, str(Path(__file__).resolve().parent))
from templates import FILLERS, TEMPLATES  # noqa: E402

HERE = Path(__file__).resolve().parent
SEED = 21
PER_TEMPLATE = 10     # filled variants per template that has {fillers}
AUG_PER_SAMPLE = 2    # noisy copies per filled sentence (Latin script only)
AUG_FIXED = 4         # noisy copies for templates without fillers

SWAPS = [("3", "a"), ("9", "q"), ("7", "h"), ("ou", "o"), ("ou", "u"), ("ch", "sh"), ("kh", "5"),
         ("gh", "r"), ("e", ""), ("a", "aa"), ("i", "ii"), ("dyal", "d"), ("wach", "wesh"), ("bghit", "bghyt")]


def fill(template: str, rng: random.Random) -> str:
    out = template
    for key, values in FILLERS.items():
        token = "{" + key + "}"
        while token in out:
            out = out.replace(token, rng.choice(values), 1)
    return out


def augment(text: str, rng: random.Random) -> str:
    if any("؀" <= c <= "ۿ" for c in text):
        return text  # Arabic script: spelling noise rules above do not apply
    t = text
    for a, b in rng.sample(SWAPS, k=2):
        if a in t and rng.random() < 0.7:
            # Replace one occurrence, not all: real typos are local.
            i = t.find(a)
            t = t[:i] + b + t[i + len(a):]
    if rng.random() < 0.3:
        t = t.capitalize()
    if rng.random() < 0.2:
        t = t + rng.choice(["?", " ??", "!", " svp", " 3afak", " please", " merci"])
    return t


def build() -> tuple[list[dict], list[dict]]:
    rng = random.Random(SEED)
    rows: list[dict] = []
    seen: set[str] = set()
    for intent, templates in TEMPLATES.items():
        for tpl in templates:
            for _ in range(PER_TEMPLATE if "{" in tpl else 1):
                base = fill(tpl, rng)
                n_aug = AUG_PER_SAMPLE if "{" in tpl else AUG_FIXED
                variants = [base] + [augment(base, rng) for _ in range(n_aug)]
                for v in variants:
                    key = v.lower().strip()
                    if key in seen:
                        continue
                    seen.add(key)
                    rows.append({"text": v, "intent": intent, "template": tpl})

    corrections = HERE / "corrections.jsonl"
    if corrections.exists():
        for line in corrections.read_text(encoding="utf-8").splitlines():
            if line.strip():
                r = json.loads(line)
                if r["text"].lower().strip() not in seen:
                    seen.add(r["text"].lower().strip())
                    rows.append({"text": r["text"], "intent": r["intent"], "template": "correction:" + r["text"]})

    # Split by template, not by row: variants of one template must not sit on both
    # sides, or validation would only measure memorisation.
    templates = sorted({r["template"] for r in rows})
    rng.shuffle(templates)
    val_templates = set(templates[: len(templates) // 6])
    train = [r for r in rows if r["template"] not in val_templates]
    val = [r for r in rows if r["template"] in val_templates]
    return train, val


def write(path: Path, rows: list[dict]) -> None:
    with path.open("w", encoding="utf-8") as f:
        for r in rows:
            f.write(json.dumps({"text": r["text"], "intent": r["intent"], "group": r["template"]}, ensure_ascii=False) + "\n")


if __name__ == "__main__":
    train, val = build()
    write(HERE / "train.jsonl", train)
    write(HERE / "val.jsonl", val)
    from collections import Counter
    print(f"train {len(train)}  val {len(val)}")
    for intent, n in sorted(Counter(r["intent"] for r in train).items()):
        print(f"  {intent:<14}{n}")
