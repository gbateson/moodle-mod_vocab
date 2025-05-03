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
 * tool/import/lang/en/vocabtool_import.php
 *
 * @package    vocabtool_import
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

$string['actionaddandupdate'] = '新しい単語を追加し、既存の単語を更新';
$string['actionaddnewonly'] = '新しい単語のみ追加（既存の単語はスキップ）';
$string['actionaddupdateremove'] = '新しい単語を追加、既存の単語を更新、不足している単語を削除';
$string['actionupdateexisting'] = '既存の単語を更新（新しい単語はスキップ）';
$string['addedrecordtotable'] = '新しい {$a->recordtype} を {$a->tabletype} テーブルに追加しました。';
$string['addrecordtotable'] = '新しい {$a->recordtype} を {$a->tabletype} テーブルに追加';
$string['datafile'] = 'データファイル';
$string['datafile_help'] = 'この活動にインポートする単語データを含むファイル（Excel、OpenOffice、CSV、またはプレーンテキスト）を追加してください。';
$string['emptydatafile'] = 'データファイルが空、存在しない、または読み取り不能です。';
$string['emptyxmlfile'] = 'XMLファイルが空または存在しません。';
$string['errorsfound'] = '{$a} 件のエラーが見つかりました';
$string['explaindata'] = '"data" {$a} には実際のデータ値が含まれます。';
$string['explainmeta'] = '"meta" {$a} には見出しと設定が含まれます';
$string['explainname'] = '"{$a}name" を使用して名前を指定します（例：{$a}name="VALUE(word)"）';
$string['explainsettings'] = 'フォーム設定のデフォルト値は、名前と値を指定することで上書きできます。';
$string['explainskip'] = '"{$a}skip" を使ってスキップ条件を定義します（例：{$a}skip="EMPTY(word)"）';
$string['explainstartend'] = '"{$a}type" の "{$a}start"（デフォルト=1）および "{$a}end"（デフォルト=最終）を定義します';
$string['fieldaccessnotallowed'] = 'このツールは、テーブル "{$a->tablename}" のフィールド "{$a->fieldname}" にアクセスできません。';
$string['formatfile'] = 'フォーマットファイル';
$string['formatfile_help'] = 'データファイルの内容の形式を指定するXMLファイルを追加します。';
$string['headingsandpreviewresults'] = '以下に、最初の {$a} 行の見出しと予測される結果が表示されます。';
$string['headingsandpreviewrows'] = '以下に、最初の {$a} 行の見出しとデータが表示されます。';
$string['headingsandresults'] = '{$a} の対象行の見出しと結果を以下に表示します。';
$string['idparametermissing'] = '{$a->tablename} テーブルで ID を取得/作成できません：{$a->fieldname} の値が不足しています。';
$string['ignorevalues'] = '無視する値';
$string['ignorevalues_help'] = '入力ファイル内で無視（空と見なす）すべき値のカンマ区切りリストです。';
$string['import'] = 'データをインポート';
$string['invalidxmlfile'] = 'XMLファイルの内容が無効です。';
$string['missingfielddata'] = 'テーブル "{$a->tablename}" のフィールド "{$a->fieldname}" に対するデータが不足しています';
$string['pluginname'] = '辞書および単語データのインポート';
$string['preview'] = '生データのプレビュー';
$string['previewrows'] = 'プレビュ行数';
$string['previewrows_help'] = 'インポートファイルからプレビューしたい行数を選択します。';
$string['privacy:metadata'] = 'vocabtool_import プラグインは、個人データを保存しません。';
$string['recordsadded'] = '{$a} 件のレコードが追加されました';
$string['recordsfound'] = '{$a} 件のレコードが見つかりました';
$string['recordswillbeadded'] = '{$a} 件のレコードが追加される予定です';
$string['review'] = '整形済みデータの確認';
$string['row'] = '行';
$string['rowsfound'] = '{$a} 行が見つかりました';
$string['sheet'] = 'シート';
$string['showsampleformatxml'] = 'フォーマットファイルのサンプルXMLコードを以下に示します：';
$string['tableaccessnotallowed'] = 'このツールは、テーブル "{$a}" にアクセスできません。';
$string['targetsheetrowcount'] = 'フォーマットファイル "{$a->filename}" は {$a->sheetcount} 枚のシートにある {$a->rowcount} 行のデータを対象としています。';
$string['totalsheetrowcount'] = 'データファイル "{$a->filename}" は {$a->sheetcount} 枚のシートと {$a->rowcount} 行のデータを含んでいます。';
$string['tryagain'] = '戻ってもう一度お試しください。';
$string['uploadaction'] = 'アップロードアクション';
$string['uploadaction_help'] = 'アップロードされたファイル内の各データ行に対して実行されるアクションを選択します。';
$string['valueshortened'] = '"{$a->fieldname}" の値は、最大長 {$a->maxlength} 文字に収まるよう短縮されました。';
$string['vocab_attribute_names'] = '語彙属性名';
$string['vocab_attribute_values'] = '語彙属性の値';
$string['vocab_attributes'] = '語彙属性';
$string['vocab_corpuses'] = 'コーパス（文章の集合）';
$string['vocab_definitions'] = '定義';
$string['vocab_frequencies'] = '出現頻度';
$string['vocab_langnames'] = '言語名';
$string['vocab_langs'] = '言語コード';
$string['vocab_lemmas'] = 'レマ（辞書の見出し語）';
$string['vocab_levelnames'] = 'レベル名';
$string['vocab_levels'] = 'レベルコード';
$string['vocab_multimedia'] = 'マルチメディアファイル';
$string['vocab_pronunciations'] = '発音';
$string['vocab_relationship_names'] = '語彙関係の名前';
$string['vocab_relationships'] = '語彙関係';
$string['vocab_words'] = '単語';
$string['xmltagmissing'] = 'XMLファイルから期待されるタグ {$a} が見つかりませんでした。';
