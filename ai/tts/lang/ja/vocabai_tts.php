<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * tool/import/lang/en/vocabai_tts.php
 *
 * @package    vocabai_tts
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

$string['cannoteditkeys'] = 'これらのキーは編集できません。';
$string['confirmcopykey'] = 'このキーをコピーしてもよろしいですか？';
$string['confirmdeletekey'] = 'このキーを削除してもよろしいですか？';
$string['copycancelled'] = 'キーのコピーはキャンセルされました。';
$string['copycompleted'] = 'キーは正常にコピーされました。';
$string['copykey'] = 'OpenAI TTS の API キーをコピーする';
$string['deletecancelled'] = 'キーの削除はキャンセルされました。';
$string['deletecompleted'] = 'キーは正常に削除されました。';
$string['deletekey'] = 'OpenAI TTS の API キーを削除する';
$string['editcancelled'] = 'キーの編集はキャンセルされました。';
$string['editcompleted'] = 'キーは正常に保存されました。';
$string['nokeysfound'] = 'キーが見つかりません';
$string['note'] = '注記';
$string['pluginname'] = '語彙活動のための OpenAI TTS AI アシスタント';
$string['privacy:metadata'] = 'vocabai_tts プラグインは個人データを保存しません。';
$string['response_format'] = '音声フォーマット';
$string['response_format_help'] = '生成された音声が返されるファイル形式です。';
$string['response_formataac'] = 'AAC形式';
$string['response_formatflac'] = 'FLAC形式';
$string['response_formatmp3'] = 'MP3形式';
$string['response_formatopus'] = 'OPUS形式';
$string['response_formatpcm'] = 'PCM形式';
$string['response_formatwav'] = 'WAV形式';
$string['speed'] = 'スピード';
$string['speed_help'] = '生成される音声の速度です。通常、再生時に調整できるため、この設定はデフォルト値（1.0）のままにしておくのが最適です。';
$string['tts'] = 'OpenAI TTS';
$string['tts-1'] = '迅速かつ高品質に音声を生成します。';
$string['tts-1-hd'] = '高品質で詳細な音声を生成し、プロンプトの精度も向上します。';
$string['ttskey'] = 'TTS キー';
$string['ttskey_help'] = 'OpenAI TTS の API にアクセスするために必要なキーです。通常は "sk-" で始まり、48文字のランダムな英数字が続きます。';
$string['ttsmodel'] = 'TTS モデル';
$string['ttsmodel_help'] = '使用する OpenAI TTS モデル（例：tts-1、tts-1-hd）を指定します。';
$string['ttsmodelid'] = 'TTS チューニング済みモデル';
$string['ttsmodelid_help'] = 'チューニングファイルを使って調整された OpenAI TTS モデルです。';
$string['ttsurl'] = 'TTS URL';
$string['ttsurl_help'] = 'OpenAI TTS の API の URL（例：https://api.openai.com/v1/audio/speech）です。';
$string['voice'] = '音声';
$string['voice_help'] = '音声を生成する際に使用する声の種類を選択します。';
$string['voicealloy'] = 'Alloy（男性、米国）';
$string['voiceecho'] = 'Echo（男性、米国）';
$string['voicefable'] = 'Fable（男性、英国）';
$string['voicefemale'] = '女性（ランダム選択）';
$string['voicemale'] = '男性（ランダム選択）';
$string['voicenova'] = 'Nova（女性、米国）';
$string['voiceonyx'] = 'Onyx（男性、米国）';
$string['voicerandom'] = 'ランダムに選ばれた声';
$string['voiceshimmer'] = 'Shimmer（女性、米国）';
