Vocabulary Activity module for Moodle >= 4.x

===============================================
OVERVIEW
===============================================

This module is designed to help students study the vocabulary of a target language.

In a Vocabulary activity, students aim to master each word in a "word list" that is
specified either by the teacher, the student, or automatically by the software.
Students interact with the words in a selection of "games". After a specified number
of successful interactions with a word, the student is judged to have mastered it.

===============================================
AI
===============================================

The question bank tool uses AI to generate questions about the words in a word list,
with images and audio. It uses the AI bridge (tool_aibridge), which must be installed:
https://github.com/gbateson/moodle-tool_aibridge

AI providers, prompts and output formats are set up in the AI bridge, by the site or
by teachers for their courses. The activity adds TOEIC-style prompts and formats to
the bridge's prompt library. Settings made with earlier versions of this activity
(ChatGPT, DALL-E, Imagen, TTS, prompts, formats and tuning files) are moved into the
AI bridge when the activity is upgraded.

===============================================
LICENSE
===============================================

This plugin is distributed under the terms of the General Public License
(see http://www.gnu.org/licenses/gpl.txt for details)

This software is provided "AS IS" without a warranty of any kind.

===============================================
CREDITS
===============================================

This module was designed and developed by Gordon Bateson
of Kochi University of Technology in Japan.
