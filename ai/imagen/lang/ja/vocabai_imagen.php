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
 * tool/import/lang/en/vocabai_imagen.php
 *
 * @package    vocabai_imagen
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

$string['aspectratio'] = 'アスペクト比';
$string['aspectratio_help'] = '生成される画像の幅と高さの比率（例：4:3、16:9）を指定します。

4:3 や 16:9 の比率は横長の「ランドスケープ」画像を生成します。

3:4 や 9:16 の比率は縦長の「ポートレート」画像を生成します。

4:3 および 3:4 は「標準画面」比率であり、16:9 および 9:16 は「ワイドスクリーン」比率です。';
$string['cannoteditkeys'] = 'これらのキーは編集できません。';
$string['compressionquality'] = '画質';
$string['compressionquality_help'] = '出力形式が JPG の場合の圧縮レベルを指定します。許可される値は 0〜100 で、デフォルト値は 75 です。';
$string['confirmcopykey'] = 'このキーをコピーしてもよろしいですか？';
$string['confirmdeletekey'] = 'このキーを削除してもよろしいですか？';
$string['copycancelled'] = 'キーのコピーはキャンセルされました。';
$string['copycompleted'] = 'キーは正常にコピーされました。';
$string['copykey'] = 'Google Imagen の API キーをコピー';
$string['deletecancelled'] = 'キーの削除はキャンセルされました。';
$string['deletecompleted'] = 'キーは正常に削除されました。';
$string['deletekey'] = 'Google Imagen の API キーを削除';
$string['dimensions'] = '画像サイズ';
$string['dimensions_help'] = 'Moodle に保存する際の画像の最大幅および高さを指定します。画像は指定された制限を超えないようにリサイズされます。元画像の縦横比は保持されます。';
$string['editcancelled'] = 'キーの編集はキャンセルされました。';
$string['editcompleted'] = '変更されたキーは正常に保存されました。';
$string['imagen'] = 'Google Imagen';
$string['imagenkey'] = 'Imagen キー';
$string['imagenkey_help'] = 'Google Imagen API にアクセスするためのキーです。通常は "sk-" で始まり、48 文字のランダムな英数字が続きます。';
$string['imagenmodel'] = 'Imagen モデル';
$string['imagenmodel_help'] = '使用する Google Imagen モデル（例：imagen-3）を指定します。';
$string['imagenmodelid'] = 'チューニング済み Imagen モデル';
$string['imagenmodelid_help'] = 'このチューニングファイルを使用して調整された Google Imagen モデルです。';
$string['imagenurl'] = 'Imagen の URL';
$string['imagenurl_help'] = 'Google Imagen API の URL（例：https://generativelanguage.googleapis.com/v1）。最新版のリリースは "beta" を URL に付け加えることで利用できる場合があります。';
$string['keeporiginals'] = '元の画像を保持';
$string['keeporiginals_help'] = '**いいえ**
画像のファイル形式、画質、またはサイズが変更された場合、元の画像は削除され、変換後の画像のみが保持されます。

**はい**
Google Imagen から取得した高解像度の元画像は常に保持されます。

元のファイルサイズは通常 2〜3 MB、変換後のファイルサイズは約 500 KB です。';
$string['maxheight'] = '最大高さ';
$string['maxheight_help'] = 'Moodle に保存される画像の最大高さ（ピクセル単位）です。';
$string['maxwidth'] = '最大幅';
$string['maxwidth_help'] = 'Moodle に保存される画像の最大幅（ピクセル単位）です。';
$string['mimetype'] = '生成されたファイルの MIME タイプ';
$string['mimetype_elements'] = '画像形式';
$string['mimetype_elements_help'] = '生成された画像の MIME タイプ（ファイル形式）を指定します。Google Imagen モデルは PNG または JPG を生成し、Moodle 内で他の形式に変換することも可能です。';
$string['mimetype_help'] = 'Google Imagen モデルは PNG または JPG を生成し、Moodle 内で他形式に変換できます。';
$string['mimetypeconvert'] = 'Moodle 内での MIME タイプ';
$string['mimetypeconvert_help'] = 'Moodle に保存する際に使用する画像の MIME タイプです。';
$string['mimetypefile'] = '{$a} ファイル';
$string['nokeysfound'] = 'キーが見つかりません';
$string['note'] = '注記';
$string['persongeneration'] = '人物の生成';
$string['persongeneration_allowadult'] = '大人の人物のみを含むことを許可';
$string['persongeneration_allowall'] = '全年齢の人物を含むことを許可';
$string['persongeneration_default'] = 'デフォルト（大人の人物のみを含むことを許可）';
$string['persongeneration_dontallow'] = '人物や顔を含む画像は生成しない';
$string['persongeneration_help'] = '生成される画像に人物を含めるかどうかを指定します。';
$string['pluginname'] = '語彙活動のための Google Imagen AI アシスタント';
$string['privacy:metadata'] = 'vocabai_imagen プラグインは個人データを保存しません。';
$string['ratiodescription'] = '{$a->ratio}（{$a->orientation}、{$a->size}）';
$string['ratiolandscape'] = 'ランドスケープ';
$string['rationormal'] = '標準';
$string['ratioportrait'] = 'ポートレート';
$string['ratiosquare'] = 'スクエア';
$string['ratiowide'] = 'ワイド';
$string['samplecount'] = '画像の数';
$string['samplecount_help'] = '生成するサンプル画像の数です。1〜4 の範囲で指定可能で、デフォルトは 2 です。

すべてのバリエーションは、指定されたファイル形式、画質、およびサイズに変換されて Moodle に保存されます。最初の画像は質問に挿入され、その他は「埋め込みファイル」としてアクセス可能です。';
