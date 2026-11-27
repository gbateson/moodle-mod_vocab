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
 * lang/en/vocab.php: English strings for mod_vocab.
 *
 * @package    mod_vocab
 * @copyright  2023 Gordon BATESON
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @author     Gordon BATESON https://github.com/gbateson
 * @since      Moodle 3.11
 */

defined('MOODLE_INTERNAL') || die();

$string['activityclose'] = '活動の終了日時';
$string['activityclose_help'] = '学生は、ここで指定された日時までこの活動にアクセスできます。この日時を過ぎると、活動は終了します。';
$string['activityname'] = '活動名';
$string['activityname_help'] = 'これは語彙活動の名前です。名前はコースページおよび活動内に表示されます。';
$string['activityopen'] = '活動の開始日時';
$string['activityopen_help'] = '学生は、この日時以降にこの活動へアクセスできます。この日時以前は、活動は利用できません。';
$string['addgame'] = 'ゲームを追加する';
$string['addinggame'] = '{$a}ゲームを追加';
$string['addnewkey'] = '新しいキーを追加';
$string['ai_generated'] = 'AI';
$string['aibridgeautomatic'] = '自動';
$string['aibridgepromptformat'] = 'プロンプトの出力形式';
$string['aibridgetuningnotstarted'] = 'このトレーニングファイルは語彙アクティビティから移動されました。モデルのチューニングに使われていなかったため、ファインチューニングのジョブは開始されていません。開始するには、ファイルをダウンロードして、このページで再度追加してください。';
$string['ais'] = 'AIアシスタント';
$string['anyattempts'] = 'すべての試行';
$string['anywordscores'] = 'すべての語彙スコア';
$string['attemptcount'] = '最小試行回数';
$string['attemptcount_help'] = '語彙の習得を示すために必要な成功試行の回数です。';
$string['attemptdelay'] = '試行間の最小遅延';
$string['attemptdelay_help'] = '試行間の最小遅延です。この設定により、学生が短期間で詰め込み学習するのを防ぐことができます。';
$string['attemptduration'] = '試行の最大合計時間';
$string['attemptduration_help'] = 'スコアと回数の条件を満たす試行に対する最大合計時間です。';
$string['attemptscore'] = '最小試行スコア';
$string['attemptscore_help'] = '語彙に対する成功とみなされる試行に必要な最小スコアです。';
$string['attempttype'] = '試行タイプ';
$string['attempttype_help'] = '試行回数条件に考慮される試行のタイプです。

**すべての試行**
すべての試行が考慮されます。

**最新の試行**
最新の試行のみが考慮されます。

**連続した試行**
連続する一連の試行が考慮されます。';
$string['audioassistant'] = 'AI音声アシスタント';
$string['audioassistant_help'] = '質問に埋め込まれる音声を生成するAI音声アシスタントを選択します。';
$string['choosegame'] = '追加するゲームを選んでください';
$string['clicktoaddaudio'] = 'ここをクリックしてAI音声アシスタントを追加';
$string['clicktoaddimage'] = 'ここをクリックしてAI画像アシスタントを追加';
$string['clicktocontinue'] = 'クリックして続けてください';
$string['completed'] = '完了';
$string['confirmdeletegame'] = 'ゲーム「{$a}」をこの活動から削除しますか？';
$string['confirmhidegame'] = 'ゲーム「{$a->title}」は{$a->count}回プレイされているため、削除せずに非表示にします。学生のスコアは保持され、後で再び表示することができます。';
$string['consecutiveattempts'] = '連続した試行';
$string['convertto'] = '変換先';
$string['coursename'] = 'コース名';
$string['customname'] = 'カスタム名';
$string['customtags'] = 'カスタムタグ';
$string['defaultregion'] = 'デフォルトのページ領域';
$string['deletegame'] = 'ゲームを削除する';
$string['demonstrationmode'] = 'デモモード';
$string['dictionaryreplybadlevel'] = 'レベル「{$a}」は不明のため、省略しました。';
$string['dictionaryreplybadpos'] = '品詞「{$a}」は不明のため、省略しました。';
$string['dictionaryreplybadrelation'] = '関係または語がないか不明のため、関連語を省略しました：{$a}';
$string['dictionaryreplyempty'] = 'AIの応答が空でした。';
$string['dictionaryreplyemptysense'] = '語義 {$a} には意味・定義・訳語がないため、省略しました。';
$string['dictionaryreplyformnotfound'] = '語「{$a->form}」が例文「{$a->sentence}」にないため、語を省略しました。';
$string['dictionaryreplynodefinition'] = 'AIの応答に定義も訳語もありませんでした。';
$string['dictionaryreplynorelated'] = 'AIの応答に関連語がありませんでした。';
$string['dictionaryreplynosamples'] = 'AIの応答に例文がありませんでした。';
$string['dictionaryreplynosenses'] = 'AIの応答に語義がありませんでした。';
$string['dictionaryreplynotjson'] = 'AIの応答がJSON形式ではありませんでした：{$a}';
$string['dictionaryreplysamesense'] = '語義 {$a} は前の語義と同じため、省略しました。';
$string['dictionaryreplyshortened'] = '「{$a}」の値を255文字に短縮しました。';
$string['dragtodeletegame'] = 'ゲームをここにドラッグすると、この活動から削除されます';
$string['editgame'] = 'ゲームを編集する';
$string['editinggame'] = '{$a}ゲームを編集';
$string['errorcompletecount'] = '単語を完了するための成功回数は、合格するための回数より少なくできません。';
$string['errorgamenotavailable'] = 'このゲームは利用できません。';
$string['errornostages'] = '少なくとも1つの段階を選んでください。';
$string['errornostagesforgame'] = '{$a}ゲームは、この活動で有効になっているどの段階でも使用できません。活動の設定で段階を追加で有効にしてください。';
$string['errorunknowngame'] = '不明なゲーム：{$a}';
$string['expandforeveryone'] = '常に展開（デフォルト）';
$string['expandfornoone'] = '常に折りたたみ';
$string['expandforstudents'] = '学生に対して展開';
$string['expandforteachers'] = '教師に対して展開';
$string['expandingdelay'] = '間隔を空けた再出現（Spaced Repetition）';
$string['expandnavigation'] = 'ナビゲーションを展開';
$string['expandnavigation_help'] = 'グローバルナビゲーションメニューを誰に対して展開するかを指定できます。';
$string['export'] = 'エクスポート';
$string['exportcompleted'] = 'エクスポートが完了しました';
$string['exportfile'] = 'エクスポートファイル名';
$string['exportfile_help'] = 'この単語リストをファイルにエクスポートできます。ファイル名を自分で定義するか、デフォルト名を使用できます。エクスポートされたファイルはバックアップとして保持したり、別の語彙活動にインポートしたりできます。';
$string['filename'] = 'ファイル名';
$string['findmissingfiles'] = '不足しているファイルを探す';
$string['fixeddelay'] = '固定遅延';
$string['gamecompleted'] = 'すべての単語を完了しました';
$string['gamedeleted'] = 'ゲームを削除しました。';
$string['gamehidden'] = 'ゲームを学生に対して非表示にしました。';
$string['gamehiddennotdeleted'] = 'このゲームはプレイされているため、削除せずに非表示にしました。学生のスコアは保持されます。';
$string['gamelocked'] = '先に前の段階で単語に合格してください';
$string['gamenotready'] = 'まだ準備ができていません';
$string['gamenowords'] = 'まだ単語がありません';
$string['gameproblem'] = '確認が必要です';
$string['gameproblemsubject'] = '{$a->activity}: ゲーム「{$a->game}」の確認が必要です';
$string['gameresume'] = '続ける';
$string['games'] = 'ゲーム';
$string['games_help'] = 'このアクティビティで学生が遊べるゲームと、それを提供する段階です。学生は各段階で遊ぶゲームを選びます。';
$string['gamesaddaftersaving'] = 'この活動を保存した後、活動ページでゲームを追加できます。';
$string['gamesaved'] = 'ゲームを保存しました。';
$string['gamesclose'] = 'ゲーム利用期限';
$string['gamesclose_help'] = '学生は、この日時までゲームを閲覧および操作できます。この日時以降はアクセスできませんが、結果の閲覧は可能です。';
$string['gameshown'] = 'ゲームを学生に再び表示しました。';
$string['gamesmanagedonpage'] = 'ゲームは活動ページで追加・並べ替えを行います。';
$string['gamesopen'] = 'ゲーム開始日時';
$string['gamesopen_help'] = '学生は、この日時以降にゲームを閲覧および操作できます。この日時以前はアクセスできません。';
$string['gamestage'] = '段階';
$string['gamestage_help'] = '学生がこのゲームをプレイする学習の段階です。この活動で有効になっていて、かつこのゲームが対応している段階だけを選べます。';
$string['gametitle'] = 'タイトル';
$string['gametitle_help'] = '活動ページに表示されるゲームのタイトルです。空欄の場合は、ゲーム名と段階からなる推奨タイトル（例：「クイズ（認識する）」）が使われます。';
$string['generateas'] = '生成形式';
$string['glosslang'] = '学生の言語';
$string['glosslang_help'] = '学生の母語です。AIは単語の訳語（短い訳）と例文の翻訳をこの言語で書きます。';
$string['gradecount'] = '最小語彙数';
$string['gradecount_help'] = 'この活動を完了するために学生が習得する必要がある語彙数です。未設定、"0"、または語彙リストの項目数より大きい場合、すべての語彙を習得する必要があります。';
$string['gradedesc'] = '成績は、以下に定義された「習得条件」に基づいて、習得済み語彙の割合で設定されます。';
$string['grademax'] = '最大成績';
$string['grademax_help'] = 'この活動の最大成績を指定します。0に設定すると、この活動は成績ページに表示されません。';
$string['gradepartial'] = '部分的に完了した語彙を含める';
$string['gradepartial_help'] = '活動の成績に部分的に完了した語彙のスコアを含めるかどうかを選択します。部分的に完了した語彙とは、学習済みだが「習得条件」にまだ完全に達していない語彙を指します。';
$string['gradetype'] = '語彙スコアの種類';
$string['gradetype_help'] = '活動の成績計算時に考慮する語彙スコアの種類です。

**最高スコア**
すべての語彙の試行スコアの中で最も高いものが使用されます。

**最低スコア**
すべての語彙の試行スコアの中で最も低いものが使用されます。

**最新スコア**
直近で完了した語彙のみが考慮されます。

**最初のスコア**
最も早く完了した語彙のみが考慮されます。';
$string['guestsnotallowed'] = 'ゲストはこの語彙活動にアクセスできません。ログインしてもう一度お試しください。';
$string['hidegame'] = 'ゲームを非表示にする';
$string['highestwordscores'] = '最高語彙スコア';
$string['imageassistant'] = 'AI画像アシスタント';
$string['imageassistant_help'] = '質問に埋め込む画像を生成するAI画像アシスタントを選択してください。';
$string['import'] = 'インポート';
$string['importcompleted'] = 'インポートが完了しました';
$string['importfile'] = 'インポートファイル';
$string['importfile_help'] = 'ここでは、ファイルから単語リストをインポートできます。1行に1語または1フレーズを記載した独自のテキストファイルを作成するか、他の語彙活動からエクスポートされたファイルを使用できます。';
$string['inprogress'] = '進行中';
$string['key'] = 'キー';
$string['keysownedbyme'] = '自分が所有しているキー';
$string['keysownedbyothers'] = '他のユーザーが所有しているキー';
$string['keysownedbyotherusers'] = '他のユーザーが所有しているキー';
$string['langmenu'] = '言語メニュー';
$string['layoutbase'] = '基本レイアウト';
$string['layoutembedded'] = '埋め込みレイアウト';
$string['layoutlogin'] = 'ログインレイアウト';
$string['layoutmaintenance'] = 'メンテナンスレイアウト';
$string['layoutpopup'] = 'ポップアップレイアウト';
$string['layoutsecure'] = 'セキュアレイアウト';
$string['layoutstandard'] = '標準レイアウト';
$string['livemode'] = 'ライブモード';
$string['lowestwordscores'] = '最低語彙スコア';
$string['makematerials'] = '不足している教材を作る';
$string['managequestioncategories'] = 'ここをクリックして質問カテゴリを管理';
$string['masteryconditions'] = '習得条件';
$string['materials'] = '学習教材';
$string['materials_help'] = '各ゲームが単語に必要とする教材（例：クイズの問題）です。ゲームには、必要な教材がある単語だけが渡されます。不足している教材は、AIを使ってバックグラウンドで作ることができます。バックグラウンドタスクはMoodleのcronが実行されると動きます。';
$string['materialscannotmake'] = 'このゲームは自分で教材を作れません。';
$string['materialsmissing'] = '{$a} 不足';
$string['materialsnotqueued'] = '教材を作れませんでした。AIブリッジとAIポリシーを確認してください。';
$string['materialspending'] = '{$a} 作成中';
$string['materialsqueued'] = '{$a} 語の教材をバックグラウンドで作っています。Moodleのcronが実行された後に準備ができます。';
$string['materialsqueuedone'] = '1 語の教材をバックグラウンドで作っています。Moodleのcronが実行された後に準備ができます。';
$string['materialsready'] = '{$a->total} 語のうち {$a->ready} 語に教材があります';
$string['medianotcreated'] = 'メディアの作成に失敗しました：{$a->subplugin}。 [filearea={$a->filearea}, itemid={$a->itemid}]';
$string['mediatype'] = 'メディアタイプ';
$string['messageprovider:gameproblem'] = '語彙アクティビティのゲームの問題（例：問題カテゴリの削除）';
$string['missingfilescourseid'] = 'コースのIDを指定してください（例：missingfiles.php?courseid=4）。';
$string['modulename'] = '語彙活動';
$string['modulename_help'] = 'Vocabモジュールは、間隔反復を通して学生の語彙習得を支援します。

1つの語彙活動には、学生が学習対象とする語彙リストが含まれます。教師は目標語彙を指定することも、学生のレベルに応じてソフトウェアに語彙を選ばせることもできます。

学生は、ゲーム形式のさまざまな活動を通して語彙に慣れていきます。知らない語彙は頻繁に再登場し、既知の語彙は間隔をあけて再登場します。';
$string['modulename_link'] = 'mod/vocab/view';
$string['modulenameplural'] = '語彙活動';
$string['newestwordscores'] = '最新の語彙スコア';
$string['noactivityheader'] = '活動ヘッダーなし';
$string['nocompletion'] = '完了情報なし';
$string['nocoursefooter'] = 'コースフッターなし';
$string['nodescription'] = '説明なし';
$string['nofooter'] = 'フッターなし';
$string['nogamesforyou'] = 'まだ遊べるゲームはありません。';
$string['nogamesinstalled'] = 'ゲームがインストールされていません。';
$string['nogamesyet'] = 'この活動にはまだゲームがありません。＋ボタンでゲームを追加してください。';
$string['nomissingfiles'] = '不足しているファイルは見つかりませんでした。';
$string['nonavbar'] = 'ナビゲーションバーなし';
$string['notasks'] = '現在、実行待ちのアドホックタスクはありません。';
$string['notitle'] = 'タイトルなし';
$string['notstarted'] = '未開始';
$string['nowordsforyou'] = '申し訳ありません。この語彙活動には、まだ学習可能な単語が含まれていません。後でもう一度お試しください。';
$string['nowordsfound'] = 'この語彙活動には、まだ単語が登録されていません。ツールメニューから単語リストをインポートまたは作成してください。';
$string['oldestwordscores'] = '最初の語彙スコア';
$string['operationmode'] = '動作モード';
$string['operationmode_help'] = '「ライブモード」では、実際の単語リストと学生データを表示します。「デモモード」では、サンプルの語彙リストと学生結果を表示し、実際のデータの見え方を体験できます。';
$string['otherkeysownedbyme'] = '自分が所有する他のキー';
$string['pagelayout'] = 'ページレイアウト';
$string['pagelayout_help'] = 'この語彙活動のメイン表示ページに使用するレイアウトを指定できます。';
$string['pagelayouts'] = 'ページレイアウト一覧';
$string['parentcategory'] = '親カテゴリ';
$string['parentcategory_help'] = '新しい質問を追加する質問カテゴリを選択してください。';
$string['pluginadministration'] = 'Vocab 管理';
$string['pluginname'] = 'Vocab';
$string['privacy:attempt'] = '受験 {$a}';
$string['privacy:metadata:core_question'] = 'クイズなど問題を使うゲームは、学生の解答を問題エンジンに保存します。';
$string['privacy:metadata:vocab_game_attempts'] = '語彙アクティビティのゲームへの学生の受験。';
$string['privacy:metadata:vocab_game_attempts:score'] = '受験の得点（0～100）。';
$string['privacy:metadata:vocab_game_attempts:stage'] = 'ゲームを遊んだ学習の段階。';
$string['privacy:metadata:vocab_game_attempts:status'] = '受験が終了したかどうか。';
$string['privacy:metadata:vocab_game_attempts:timecompleted'] = '受験を終了した日時。';
$string['privacy:metadata:vocab_game_attempts:timestarted'] = '受験を開始した日時。';
$string['privacy:metadata:vocab_game_attempts:userid'] = '受験した学生。';
$string['privacy:metadata:vocab_word_attempts'] = 'ゲームの受験での各単語の得点。';
$string['privacy:metadata:vocab_word_attempts:score'] = '単語の得点（0～100）。';
$string['privacy:metadata:vocab_word_attempts:timecreated'] = '単語に得点が付いた日時。';
$string['privacy:metadata:vocab_word_stages'] = '各段階での各単語についての学生の進捗。';
$string['privacy:metadata:vocab_word_stages:interactions'] = 'この段階で単語に得点が付いた回数。';
$string['privacy:metadata:vocab_word_stages:stage'] = '学習の段階。';
$string['privacy:metadata:vocab_word_stages:successes'] = 'この段階で単語の合格と完了に数えられる成功の回数。';
$string['privacy:metadata:vocab_word_stages:timecompleted'] = 'この段階で単語を完了した日時。';
$string['privacy:metadata:vocab_word_stages:timepassed'] = 'この段階で単語に合格した日時。';
$string['privacy:metadata:vocab_word_stages:userid'] = '学生。';
$string['privacy:metadata:vocab_word_states'] = '各単語についての学生の全体の状態。';
$string['privacy:metadata:vocab_word_states:state'] = '単語が未開始、進行中、完了のどれか。';
$string['privacy:metadata:vocab_word_states:userid'] = '学生。';
$string['prompthead'] = 'プロンプト名（先頭）';
$string['prompttail'] = 'プロンプト名（末尾）';
$string['qformat'] = 'AI出力形式';
$string['qformat_help'] = 'AI出力をMoodleの問題集にインポートできる形式に変換するための出力形式を選択してください。';
$string['questioncount'] = '質問数';
$string['questioncount_help'] = '各レベルで生成する新しい質問の数です。';
$string['questionreview'] = '質問レビュー';
$string['questionreview_help'] = 'この設定を有効にすると、AIの結果は教師がレビューするまで問題集にインポートされません。';
$string['questiontags'] = '質問タグ';
$string['questiontags_help'] = '必要であれば、カスタムタグを1つ以上指定できます。複数のタグを追加する場合は、カンマで区切ってください。';
$string['questiontype'] = '質問タイプ';
$string['questiontype_help'] = 'AIによって生成される質問のタイプです。';
$string['recentattempts'] = '最新の試行';
$string['regions'] = 'ページ領域';
$string['reports'] = 'レポート';
$string['resultdesc'] = '{$a->label}{$a->delimiter} {$a->number}/{$a->total} ({$a->percent}%)';
$string['resultsdesc'] = '{$a->completed}, {$a->inprogress}, {$a->notstarted}';
$string['resultstitle'] = '{$a} の語彙結果';
$string['search:activity'] = '語彙アクティビティ - 情報';
$string['sectionname'] = 'セクション名';
$string['selectprompt'] = 'プロンプトを選択 …';
$string['sharedanydate'] = '無期限で共有';
$string['sharedfrom'] = '共有開始日時';
$string['sharedfrom_help'] = 'この項目は、この日時から（含む）共有されます。';
$string['sharedfromdate'] = '{$a} から共有';
$string['sharedfromuntildate'] = '{$a->from} から {$a->until} まで共有';
$string['shareduntil'] = '共有終了日時';
$string['shareduntil_help'] = 'この項目は、この日時まで（含む）共有されます。';
$string['shareduntildate'] = '{$a} まで共有';
$string['sharingperiod'] = '共有期間';
$string['showgame'] = 'ゲームを表示する';
$string['sortstrings'] = '選択されたプラグインの文字列を並べ替え';
$string['speeditemcount'] = '項目数';
$string['speeditemcount_help'] = '指定された時間内に生成できる最大項目数です。';
$string['speeditemtype'] = '項目タイプ';
$string['speeditemtype_help'] = '指定された時間内にカウント対象となる項目のタイプです。';
$string['speedtimecount'] = '時間単位の数';
$string['speedtimecount_help'] = '1つの時間期間を構成する時間単位の数です。';
$string['speedtimeunit'] = '時間単位';
$string['speedtimeunit_help'] = '時間期間を測定するために使用される時間単位の種類です。';
$string['stagecompletecount'] = '完了まで';
$string['stagecompleted'] = '完了';
$string['stagelearn'] = '学ぶ';
$string['stagelearn_help'] = '学生は各単語の意味、音、絵、使い方に出会います（例：フラッシュカード）。

各段階で、単語の状態はその段階での成功回数によって決まります。「合格まで」の回数に達すると合格となり、次の段階で提供されます。「完了まで」の回数に達すると完了となり、この段階では提供されなくなります。有効にしたすべての段階で完了すると、その単語は習得されたことになります。';
$string['stagenotenabled'] = '段階が無効です';
$string['stagenotstarted'] = '未開始';
$string['stagepasscount'] = '合格まで';
$string['stagepassed'] = '合格';
$string['stageproduce'] = '使う';
$string['stageproduce_help'] = '学生は各単語を使ってコミュニケーションします（例：書く、話す、AIとのチャット）。

各段階で、単語の状態はその段階での成功回数によって決まります。「合格まで」の回数に達すると合格となり、次の段階で提供されます。「完了まで」の回数に達すると完了となり、この段階では提供されなくなります。';
$string['stageprogress'] = '{$a->stage}: {$a->state}';
$string['stagerecall'] = '思い出す';
$string['stagerecall_help'] = '学生は選択肢なしで各単語を思い出します（例：つづりを書く、空所を埋める）。

各段階で、単語の状態はその段階での成功回数によって決まります。「合格まで」の回数に達すると合格となり、次の段階で提供されます。「完了まで」の回数に達すると完了となり、この段階では提供されなくなります。';
$string['stagerecognize'] = '認識する';
$string['stagerecognize_help'] = '学生は選択肢から各単語を選びます（例：多肢選択問題、組み合わせ）。

各段階で、単語の状態はその段階での成功回数によって決まります。「合格まで」の回数に達すると合格となり、次の段階で提供されます。「完了まで」の回数に達すると完了となり、この段階では提供されなくなります。';
$string['stages'] = '学習の段階';
$string['stagesuccess'] = '1回成功';
$string['stagesuccesses'] = '{$a}回成功';
$string['stageuse'] = '有効にする';
$string['subcategories'] = 'サブカテゴリ';
$string['subcategories_help'] = 'AIによって生成された質問を格納するための、親カテゴリ内の階層構造をこれらのチェックボックスで指定します。

**なし:** サブカテゴリは作成されません。すべての新しい質問は「親カテゴリ」に直接追加されます。

**カスタム名:** このチェックボックスを選択した場合、指定欄に質問サブカテゴリのカスタム名を入力します。

**セクション名:** この語彙活動が表示されているコースセクション（例:「トピック」「週」）に基づくカテゴリです。

**活動名:** 現在の語彙活動に基づくカテゴリです。

**単語名:** 各語彙アイテム（または「単語」）に基づくカテゴリです。

**質問タイプ:** 各質問タイプ（例:「MC」「短答」「マッチ」）に基づくカテゴリです。

**語彙レベル:** 各語彙レベル（例:「A1」「TOEFL-30」「TOEIC-300」など）に基づくカテゴリです。

**プロンプト名（先頭）:** プロンプト名の「先頭」部分に基づくカテゴリ（例："TOEIC R&L (Part 1): 画像描写" の「TOEIC R&L (Part 1)」）。

**プロンプト名（末尾）:** プロンプト名の「末尾」部分に基づくカテゴリ（例："TOEIC R&L (Part 1): 画像描写" の「画像描写」）。

指定されたサブカテゴリが存在しない場合は、質問のインポート時に自動的に作成されます。';
$string['subplugintype_vocabgame'] = 'ゲーム';
$string['subplugintype_vocabgame_plural'] = 'ゲーム';
$string['subplugintype_vocabreport'] = '語彙レポート';
$string['subplugintype_vocabreport_plural'] = '語彙レポート';
$string['subplugintype_vocabtool'] = '語彙ツール';
$string['subplugintype_vocabtool_plural'] = '語彙ツール';
$string['subplugintype_vocabutil'] = 'Vocabulary util';
$string['subplugintype_vocabutil_plural'] = 'Vocabulary utils';
$string['textassistant'] = 'AIテキストアシスタント';
$string['textassistant_help'] = '質問を生成するためのAIテキストアシスタントを選択してください。';
$string['tools'] = 'ツール';
$string['utils'] = 'Utilities';
$string['vocab:addinstance'] = '新しい語彙活動を追加';
$string['vocab:attempt'] = '語彙活動を試行';
$string['vocab:deleteattempts'] = '語彙活動の試行を削除';
$string['vocab:manage'] = '語彙活動を管理';
$string['vocab:preview'] = '語彙活動をプレビュー';
$string['vocab:reviewmyattempts'] = '自分の語彙活動試行を確認';
$string['vocab:view'] = '語彙活動を表示';
$string['vocab:viewreports'] = '語彙活動のレポートを表示';
$string['vocablevel'] = '語彙レベル';
$string['word'] = '単語';
$string['word_help'] = 'この質問が生成された対象の単語または語彙アイテムです。';
$string['wordlang'] = '単語の言語';
$string['wordlang_help'] = 'この活動で学生が学ぶ言語です。単語リストに追加する新しい単語はこの言語になり、AIはこの言語で定義と例文を書きます。';
$string['wordlist'] = '単語リスト';
$string['wordlistcontainingnwords'] = '単語リスト（{$a} 語を含む）';
$string['youneedtoenrol'] = 'この語彙活動にアクセスするには、このコースに登録する必要があります。';
