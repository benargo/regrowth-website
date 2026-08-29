---
paths:
  - app/Models/GuildRank.php
---

# Models

## GuildRank.sort_order is 0-based and tied to Blizzard's rank index
GuildRank.sort_order deliberately starts at 0, unlike other sortable models (Boss, EventAssignment*, which are 1-based via the Spatie package default). This is intentional: sort_order doubles as the Blizzard guild-roster rank index, consumed directly in FetchGuildRoster, AddonController, and GuildRosterMemberCollection. Do not "fix" this to match other models' 1-based ordering — that would require an offset translation everywhere the value is compared against Blizzard API data. See the overridden setHighestOrderNumber() in GuildRank.php.
