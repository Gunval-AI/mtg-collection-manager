from pathlib import Path
import json
import re
import time
import urllib.request


BASE_DIR = Path(__file__).resolve().parents[1]
OUTPUT_DIR = BASE_DIR / "references" / "set_symbols" / "raw"

SCRYFALL_SETS_URL = "https://api.scryfall.com/sets"
REQUEST_TIMEOUT = 30
REQUEST_DELAY_SECONDS = 0.15

TARGET_SET_NAMES = [
    "Magic: The Gathering | Teenage Mutant Ninja Turtles",
    "Lorwyn Eclipsed",
    "Magic: The Gathering | Avatar: The Last Airbender",
]


def normalize_text(text: str) -> str:
    text = text.lower()
    text = re.sub(r"[^a-z0-9]+", " ", text)
    return " ".join(text.split())


def http_get_json(url: str) -> dict:
    request = urllib.request.Request(
        url,
        headers={
            "User-Agent": "MTGCollectionManager/0.1",
            "Accept": "application/json",
        },
    )

    with urllib.request.urlopen(request, timeout=REQUEST_TIMEOUT) as response:
        return json.loads(response.read().decode("utf-8"))


def download_file(url: str, output_path: Path) -> None:
    request = urllib.request.Request(
        url,
        headers={
            "User-Agent": "MTGCollectionManager/0.1",
        },
    )

    with urllib.request.urlopen(request, timeout=REQUEST_TIMEOUT) as response:
        output_path.write_bytes(response.read())


def find_set_by_name(all_sets: list[dict], target_name: str) -> dict | None:
    normalized_target = normalize_text(target_name)

    for set_data in all_sets:
        if normalize_text(set_data.get("name", "")) == normalized_target:
            return set_data

    for set_data in all_sets:
        normalized_name = normalize_text(set_data.get("name", ""))

        if normalized_target in normalized_name or normalized_name in normalized_target:
            return set_data

    return None


def download_set_symbol(set_data: dict) -> None:
    set_code = set_data["code"].upper()
    set_name = set_data["name"]
    icon_svg_uri = set_data.get("icon_svg_uri")

    if not icon_svg_uri:
        print(f"[NO ICON] {set_code} - {set_name}")
        return

    output_path = OUTPUT_DIR / f"{set_code}.svg"

    print(f"[DOWNLOAD] {set_code} - {set_name}")
    download_file(icon_svg_uri, output_path)
    print(f"[SAVED]    {output_path}")


def main() -> None:
    OUTPUT_DIR.mkdir(parents=True, exist_ok=True)

    sets_response = http_get_json(SCRYFALL_SETS_URL)
    all_sets = sets_response.get("data", [])

    for target_name in TARGET_SET_NAMES:
        set_data = find_set_by_name(all_sets, target_name)

        if set_data is None:
            print(f"[NOT FOUND] {target_name}")
            continue

        # Be polite with the public Scryfall API.
        download_set_symbol(set_data)
        time.sleep(REQUEST_DELAY_SECONDS)


if __name__ == "__main__":
    main()