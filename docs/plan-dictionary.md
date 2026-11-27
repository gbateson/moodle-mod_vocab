# Plan: populating the dictionary

Status: 2026-11-27. Draft, for discussion. Nothing is coded yet.

## Goal

Fill in the information that mod_vocab can store about each word, so that
games (e.g. flashcards at the Learn stage) and the question bank tool can use
it. Most of it is generated with AI, through the AI bridge (tool_aibridge),
at the CEFR level that students need.

## What the tables hold now

The dictionary belongs to the whole site: activities only link to words
(`vocab_word_instances`).

| Information | Tables | Fields |
|---|---|---|
| Words and headwords | `vocab_words` → `vocab_lemmas` → `vocab_langs` | A form ("ran") points to its lemma, i.e. its dictionary form ("run"), which has a language code. |
| Attributes | `vocab_attributes`, `vocab_attribute_names`, `vocab_attribute_values` | Any name/value pair for a word, e.g. part of speech = noun. |
| Frequency | `vocab_frequencies`, `vocab_corpuses` | A frequency and a rank in a named corpus or word list (name, description, URL). |
| Definitions | `vocab_definitions` | Text (255 characters), with a language and a level. |
| Multimedia | `vocab_multimedia` | A media type (image, audio, video) and HTML, with a language and a level. |
| Pronunciation | `vocab_pronunciations` | A phonetic spelling (e.g. IPA) and an audio file, with a language. |
| Related words | `vocab_relationships`, `vocab_relationship_names` | Word → named relation (e.g. synonym, antonym) → word. |
| Example sentences | `vocab_samples`, `vocab_sample_words` | A sentence with a language and a level, linked to each word in it. |
| Levels | `vocab_levels`, `vocab_levelnames` | Level codes and their names in each language. |

On the 4.5 development site, only words, lemmas and one language have data.
The levels table is empty, so not even A1 to C2 exist yet.

## Terms

- **Lemma**: a word's dictionary form, the headword that you look up: "run"
  for "runs", "ran" and "running"; "good" for "better". It is not the root:
  "happy", "happiness" and "unhappy" share a root, but are three lemmas.
- **Sense**: one meaning of a word, like the numbered meanings in a
  dictionary entry. For example, "bank" (noun) is a place that keeps money, or
  the land beside a river; "bank" (verb) is to keep money in a bank. A
  definition, example sentence, picture or level belongs to one sense.

## What is missing

1. **Senses.** Definitions, samples and attributes are attached to a word
   form, with no part of speech or sense, so "bank" (money) and "bank"
   (river) cannot be told apart, and "runs" would need its own definitions.
   Proposal: attach them to a sense, i.e. lemma + part of speech + sense number.
2. **Source.** Nothing records where an item came from. Each definition,
   sample, pronunciation and media item should record its source, e.g.
   "AI: provider and prompt", "teacher", or "imported from (file)".
   `vocab_corpuses` could become a general table of sources.
3. **Review status.** AI content needs a status, e.g. draft, approved or
   rejected, so that games can choose what to use.
4. **Levels.** Add the CEFR levels A1 to C2, with their names in English and
   Japanese.
5. **Smaller points:**
   - 255 characters is enough for a definition, but perhaps not for notes.
   - Pronunciations have no accent; language codes such as `en_us` and
     `en_gb` might be enough.
   - The import tool (`tool/import`) still lists tables that do not exist,
     e.g. `vocab_word_samples` (the table is `vocab_sample_words`) and
     `vocab_ai_*`.
   - The "Edit dictionary data" tool (`tool/dictionary`) is only a stub.

## Decisions made

| Topic | Decision |
|---|---|
| Where definitions and senses come from | AI, through the AI bridge, at a chosen CEFR level. |
| Web dictionaries with CC BY-SA content (e.g. Wiktionary) | Not used for now. CC BY-SA (Creative Commons Attribution-ShareAlike) would require crediting the source wherever the content is shown, and sharing edited versions under the same licence. |
| Commercial dictionaries (e.g. Oxford, Cambridge, Merriam-Webster) | Not used: they need API keys, and their terms usually forbid keeping their content. |
| AI content | Marked as AI-generated (see *Source* above). |

## What AI could generate

- The senses of a lemma: part of speech and a short meaning for each.
- For each sense:
  - a definition at a chosen CEFR level, using simpler words than the word itself;
  - a short gloss in the students' language, e.g. Japanese;
  - example sentences at the level;
  - an estimated CEFR level, marked as an estimate;
  - related words, e.g. synonyms, antonyms, collocations and the word family.
- Pronunciation: IPA from a text model, and audio from a text-to-speech
  provider (the AI bridge already makes audio for questions).
- Pictures, with an image provider (the AI bridge already makes images for
  questions).

Output would be JSON, so that it can be checked and saved field by field.

## Proposed steps

1. **Data model:** senses, a source and a review status for each item, the
   CEFR levels, and fixes to the import tool's table names. Upgrade steps for
   existing data.
2. **AI prompts:** AI bridge templates, like the question bank tool's, for
   senses, definitions, glosses, samples and related words, with the CEFR
   level and the students' language as variables.
3. **Background tasks:** a "Fill in the dictionary" action for a word list,
   run as ad hoc tasks, like the question bank tool, showing progress. Items
   are saved with their source and status.
4. **Review and editing:** build out `tool/dictionary`, so that teachers can
   see a word's entry, approve, edit or reject AI items, and add their own.
5. **Using the data:** a Learn stage game (e.g. flashcards) that uses the
   materials mechanism (`gamebase::get_ready_words()`, `make_materials()`),
   as the quiz uses questions; definitions and samples in the question bank
   tool's prompts; and, perhaps, approved entries in backups.
6. **Optional, later:** import free frequency and level lists (e.g. NGSL or
   the CEFR-J Wordlist, licences to be checked) with the import tool.

## Open questions

1. Senses: attach definitions, samples and attributes to lemma + part of
   speech + sense, as proposed, or keep them per word form?
2. Review: must a teacher approve AI content before games use it, or is it
   used at once, marked as AI-generated?
3. Languages: English only to begin with, plus Japanese glosses, or general
   from the start?
4. When to fill in: automatically when words are added to a word list, or
   only when a teacher asks?
5. Which information first: definitions and samples only, or also
   pronunciation, pictures and related words?
