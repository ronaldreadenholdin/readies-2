# PSP adapter rules

Hard rule: uniform output, independent PSPs.

- The only shared PSP behavior is the explicitly versioned adapter contract (`ADP-01:v1`) and the conformance/report runner that reads that contract.
- Provider-specific code lives in `Providers/<provider_code>/`.
- A provider folder owns its converter, field map, webhook parsing/signature check, decline-code map, fixtures, golden outputs reference, and config.
- A provider folder must not import or reference another provider folder or class.
- Do not put PSP-specific headers, status maps, decline maps, field maps, fixture paths, or provider branches into shared base classes.
- Contract changes require a new version. Each PSP declares the contract version it targets and must re-pass 100% on that version.
- Go-live and cascade eligibility remain blocked unless the provider connection scores exactly 100%.

Duplicate small helpers inside a provider folder when they contain provider logic. Shared helpers are allowed only when they are contract-level and provider-neutral.
