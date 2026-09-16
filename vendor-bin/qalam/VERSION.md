# qalam — vendored binary

Correct, logical-order Arabic text extraction from digitally-born PDFs, without OCR.
Used by `App\Services\Library\QalamExtractor` for the CV-import feature (constitution §9).

- Source: https://github.com/misraj-ai/qalam
- Commit: 73c2d275649570d52b7895d2ab192253f7e5d752 (2026-09-15)
- Crate version: 0.1.1
- License: GPLv3 (see `LICENSE` in this directory) — invoked as a separate CLI subprocess,
  never linked into the PHP process, so no copyleft obligation attaches to `hc` itself.
- Built with: `cargo build --release --bin qalam` on x86_64 Linux, then `strip --strip-all`.

## Why a committed binary instead of a build step

The platform's hosting has no Rust toolchain and no external-service dependency at runtime
(constitution: "0 تكلفة ولا خدمة خارجيّة"). A vendored binary keeps the CV importer working
offline and dependency-free, matching how the project already vendors fonts and other static
assets rather than fetching them at request time.

## Rebuilding

```sh
git clone https://github.com/misraj-ai/qalam
cd qalam && git checkout 73c2d275649570d52b7895d2ab192253f7e5d752
cargo build --release --bin qalam
strip --strip-all target/release/qalam -o /path/to/hc/vendor-bin/qalam/qalam
```
