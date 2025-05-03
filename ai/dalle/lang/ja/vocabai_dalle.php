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
 * tool/import/lang/en/vocabai_dalle.php
 *
 * @package    vocabai_dalle
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

$string['cannoteditkeys'] = 'これらのキーは編集できません。';
$string['confirmcopykey'] = 'このキーをコピーしてもよろしいですか？';
$string['confirmdeletekey'] = 'このキーを削除してもよろしいですか？';
$string['copycancelled'] = 'キーのコピーはキャンセルされました。';
$string['copycompleted'] = 'キーのコピーが正常に完了しました。';
$string['copykey'] = 'DALL-E の API キーをコピー';
$string['dall-e-2'] = '高品質で創造的な画像を素早く生成します。';
$string['dall-e-3'] = '高品質かつ詳細な画像を生成し、プロンプトの精度が向上します。';
$string['dalle'] = 'OpenAI DALL-E';
$string['dallekey'] = 'DALL-E キー';
$string['dallekey_help'] = 'OpenAI DALL-E の API にアクセスするために必要なキーです。通常、"sk-" で始まり、48 文字のランダムな英数字が続きます。';
$string['dallemodel'] = 'DALL-E モデル';
$string['dallemodel_help'] = '使用する OpenAI DALL-E モデル（例：dall-e-2、dall-e-3）を指定します。';
$string['dallemodelid'] = 'チューニング済 DALL-E モデル';
$string['dallemodelid_help'] = 'このチューニングファイルを使って調整された OpenAI DALL-E モデルです。';
$string['dalleurl'] = 'DALL-E URL';
$string['dalleurl_help'] = 'OpenAI DALL-E の API の URL（例：https://api.openai.com/v1/audio/speech）を指定します。';
$string['deletecancelled'] = 'キーの削除はキャンセルされました。';
$string['deletecompleted'] = 'キーの削除が正常に完了しました。';
$string['deletekey'] = 'DALL-E の API キーを削除';
$string['editcancelled'] = 'キーの編集はキャンセルされました。';
$string['editcompleted'] = '変更されたキーが正常に保存されました。';
$string['filetype'] = 'DALL-E のファイルタイプ';
$string['filetype_elements'] = '画像ファイルタイプ';
$string['filetype_elements_help'] = 'この設定では、OpenAI DALL-E によって生成され Moodle に保存されるコンテンツのファイルタイプ（MIME タイプ）を指定します。DALL-E モデルは PNG 形式のみを生成しますが、Moodle 内で他の形式に変換できます。';
$string['filetype_help'] = 'OpenAI DALL-E モデルは PNG 形式のみを生成しますが、Moodle 内で他の形式に変換することが可能です。';
$string['filetypeconvert'] = 'Moodle 内でのファイルタイプ';
$string['filetypeconvert_help'] = 'Moodle に画像を保存する際に使用されるファイルタイプです。';
$string['filetypefile'] = '{$a} ファイル';
$string['keeporiginals'] = '元の画像を保持する';
$string['keeporiginals_help'] = '**いいえ**
画像のファイルタイプ、品質、サイズを変更した場合、元の画像は削除され、変換後の画像のみが保持されます。

**はい**
OpenAI DALL-E からの高解像度の元画像は常に保持されます。

元ファイルのサイズは通常 2〜3MB 程度で、変換後のファイルは約 500KB 程度です。';
$string['n'] = '画像数';
$string['n_help'] = '生成する画像のバリエーション数を指定します。1〜10 の間で指定可能で、デフォルトは 1 です。

すべてのバリエーションは指定されたファイルタイプ、品質、サイズに変換されて Moodle に保存されます。最初の画像は質問内に挿入され、その他は「埋め込みファイル」として利用可能になります。';
$string['nokeysfound'] = 'キーが見つかりませんでした。';
$string['note'] = '注';
$string['pluginname'] = '語彙活動用の OpenAI DALL-E AI アシスタント';
$string['privacy:metadata'] = 'vocabai_dalle プラグインは、個人データを保存しません。';
$string['quality'] = 'DALL-E の画像品質';
$string['quality_elements'] = '画像品質';
$string['quality_elements_help'] = 'OpenAI DALL-E によって生成され Moodle に保存される画像の品質を指定します。';
$string['quality_help'] = 'OpenAI DALL-E によって生成される画像の品質です。「高解像度」を指定すると、より細かいディテールと一貫性のある画像になります。このパラメータは DALL-E-3 のみで使用可能です。';
$string['qualityconvert'] = 'Moodle 内での画像品質';
$string['qualityconvert_help'] = 'Moodle に画像を保存する際の画像品質です。高い数値を指定すると、画像の解像度が上がりますが、ファイルサイズも大きくなります。';
$string['qualityhd'] = '高解像度';
$string['qualitystandard'] = '標準解像度';
$string['response_format'] = '画像形式';
$string['response_format_help'] = '生成された画像の返却形式を指定します。"url" または "b64_json" のいずれかでなければなりません。URL の有効期間は生成後 60 分間です。';
$string['response_formatb64_json'] = 'Base64 (json) 形式';
$string['response_formaturl'] = 'URL';
$string['size'] = 'DALL-E の画像サイズ';
$string['size_elements'] = '画像サイズ';
$string['size_elements_help'] = '生成される画像のサイズを指定します。OpenAI DALL-E が生成する画像はファイルサイズが比較的大きいため（2〜3MB）、小さいサイズに変換することでファイルサイズを削減できます。';
$string['size_help'] = 'OpenAI DALL-E によって生成される画像のピクセルサイズ（幅 × 高さ）です。ここに記載されたサイズのみ使用可能です。

**DALL-E-2**
256x256、512x512、または 1024x1024 のいずれか。

**DALL-E-3**
1024x1024、1792x1024、または 1024x1792 のいずれか。';
$string['sizeconvert'] = 'Moodle 内での画像サイズ';
$string['sizeconvert_help'] = 'Moodle に保存される画像のピクセルサイズ（幅 × 高さ）です。サイズを小さくすると、ファイルサイズも小さくなります。';
$string['sizelandscape'] = '▬ {$a}: 横向き';
$string['sizeportrait'] = '▮ {$a}: 縦向き';
$string['sizesquare'] = '■ {$a}: 正方形';
$string['style'] = '画像スタイル';
$string['style_help'] = '生成される画像のスタイルを指定します。"vivid" または "natural" のいずれかです。"vivid" は鮮やかでドラマチックな画像、"natural" はより自然でリアルな画像になります。このパラメータは DALL-E-3 のみで使用可能です。';
$string['stylenatural'] = 'ナチュラルスタイル';
$string['stylevivid'] = 'ビビッドスタイル';
