# Recognition scripts

Scripts used to prepare set symbols for the recognition service.

- `download_set_symbols.py`: downloads SVG symbols from Scryfall into `raw/`
- `process_set_symbols.py`: converts them into normalized PNG images in `processed/`

Run from `recognition-service`:

python scripts/download_set_symbols.py
python scripts/process_set_symbols.py