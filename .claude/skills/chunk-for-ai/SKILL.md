---
name: chunk-for-ai
description: Mechanically restructure large Markdown documents so AI agents can read them with less context. Use when asked to split, chunk, organize, index, or prepare long Markdown files for token-efficient AI reading, especially by splitting the first useful heading level into files under a subfolder and creating an index.md routing file. Prefer H1 when there are multiple H1 sections; fall back to H2 when the only H1 is the document title.
---

# Chunk for AI

## Purpose

Create a token-efficient reading surface for a large Markdown document. This first version is intentionally mechanical: choose the first useful heading level, write one file per section under a subfolder, and create an `index.md` that helps future agents choose which chunk to read.

Do not attempt semantic regrouping unless the user explicitly asks. Preserve source order.

## Workflow

1. Identify the source Markdown file and output subfolder.
   - If the user names a subfolder, use it.
   - If not, create a sibling folder named from the source filename stem.
   - Keep generated files near the source document unless the user specifies another location.

2. Inspect the source structure and choose the split level.
   - Count H1 headings with a line search such as `rg -n "^# " <file>`.
   - Count H2 headings with a line search such as `rg -n "^## " <file>`.
   - If there are multiple H1 headings, split on H1.
   - If there is only one H1 and multiple H2 headings, treat the H1 as the document title and split on H2.
   - Do not split below H2 unless the user explicitly asks.
   - If there is content before the first chosen split heading, preserve it as a leading chunk with a generated H1 based on the document title or filename.

3. Generate chunk files.
   - Use stable ordered filenames: `00-slug.md`, `01-slug.md`, etc.
   - Each chunk file must start with an H1.
   - When splitting on H2, promote the first heading of each generated chunk from `##` to `#`.
   - Preserve the original chunk body exactly apart from the first-heading promotion and trimming trailing whitespace.
   - Prefer simple ASCII slugs: lowercase, hyphen-separated, no punctuation.
   - Do not overwrite unrelated files in the output folder. If regenerating a folder, remove or replace only files that are clearly generated chunks/index for this source.

4. Generate `index.md`.
   - Keep it much smaller than the source document.
   - Include a short instruction: read this file first, then open only relevant chunks.
   - Include a table with file, H1/topic, and a brief routing note.
   - Include a keyword or minimal-reading-path section only if it can be created cheaply from headings and visible section labels.
   - For mechanical v1, summaries may be based on headings and nearby first paragraphs; do not load every chunk deeply just to write polished prose.

5. Validate.
   - Confirm the number of chunk files equals the number of chosen split headings, plus one if pre-split content was preserved.
   - Confirm every chunk starts with `# `.
   - Confirm every link in `index.md` resolves.
   - Report if the output path is ignored by git, since generated docs may not appear in `git status`.

## Suggested Index Shape

```markdown
# <Document Title> Chunk Index

Use this file first. Open only the chunk that matches the task.

| File | Topic | Route here for |
| --- | --- | --- |
| [00-title.md](00-title.md) | Title / metadata | owner, status, audience |
| [01-overview.md](01-overview.md) | Overview | purpose, mental model |

## Minimal Reading Paths

| Goal | Files |
| --- | --- |
| Implement core behavior | [03](03-core-behavior.md), [04](04-rules.md) |

## Validation

- Chunk count: N
- Missing links: 0
```

## Notes

- Prefer shell tools or a small script for deterministic splitting.
- Keep the generated structure boring and predictable.
- The value is not perfect summarization; the value is reducing future context by giving agents a small routing surface.
