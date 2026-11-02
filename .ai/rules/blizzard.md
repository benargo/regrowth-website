---
paths:
  - 'app/Http/Integrations/Blizzard/**'
---

# Blizzard

## Blizzard name slugs keep diacritics; never Str::slug()
Blizzard profile API path segments for character/guild/realm names are lowercased with `Str::lower()`, keep diacritics, and turn spaces into hyphens: `Ízepo` → `ízepo`, `Thräll Runetotem` → `thräll-runetotem`. Never use `Str::slug()`: it transliterates to ASCII (`izepo`) and silently resolves a different character.
