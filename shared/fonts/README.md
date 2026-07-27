# Bundled Arabic web font

`noto-sans-arabic.woff2` is derived from the Noto Sans Arabic variable TTF in
the [official Google Fonts repository](https://github.com/google/fonts/tree/main/ofl/notosansarabic).
The font is distributed under the SIL Open Font License 1.1; the complete
license is stored in `OFL-NotoSansArabic.txt`.

Source SHA-256:

```text
63111b5b2e074dd48cc67692e0a2726d86ee94c1c37fe8598257b7b4e87e869e
```

Rebuild the WOFF2 while preserving the complete Unicode cmap, OpenType shaping
features and both variable axes:

```bash
pyftsubset 'NotoSansArabic[wdth,wght].ttf' \
  --output-file=noto-sans-arabic.woff2 \
  --flavor=woff2 \
  --unicodes='*' \
  --layout-features='*' \
  --name-IDs='*' \
  --name-legacy \
  --name-languages='*'
```

The generated WOFF2 SHA-256 is:

```text
501c9fbf428802c6d5b34b6d610372fd599adc2f4b4c9fb34e9190c562fcfeef
```
