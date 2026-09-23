---
paths:
  - 'app/Http/Resources/**'
---

# Resources

## Guard embedded relations with whenLoaded()
When a Resource embeds a relationship as a nested Resource/Collection, wrap the access in $this->whenLoaded('relation', fn () => ...) rather than accessing it unconditionally.
