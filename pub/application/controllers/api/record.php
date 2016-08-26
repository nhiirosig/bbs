<?php
class Record extends CI_Controller {
	function __construct() {
		parent::__construct ();

		// ライブラリをロード
		$this->load->library ( "Yoyaku_c_stlib" );
		$this->load->library ( "My_smarty_lib", "", "mylib" );
	}
	/**
	 * 予約フォームを表示するためのAPI
	 */
	function reservation_form() {
		echo $this->smarty->view ( "yoyaku/part/reservation_form.html" );

		log_message ( "debug", "予約フォームAPIが呼ばれたよ" );
	}
	function get() {
		$currentgoodsid = - 10;
		$mode = 0;

		// ユーザーIDはセッションに保存されている（はず）
		$userid = isset ( $_SESSION ["userid"] ) ? $_SESSION ["userid"] : - 1;

		if ($this->input->get ( "goodsid" )) {
			$currentgoodsid = $this->input->get ( "goodsid" );

			// 渡されてきたIDが無効フラグ(-1)だった場合,セッションから読み込む
			if (($currentgoodsid == - 1) && (isset ( $_SESSION ['current_goodsid'] ))) {
				$currentgoodsid = $_SESSION ['current_goodsid'];
			}
		}
		if ($this->input->get ( "mode" )) {
			$userid = $this->input->get ( "mode" );
		}

		// 現在選択中の品目名
		$currntgoodsname = "";

		// 品目情報を取得
		$res = $this->Yoyakumodel->getgoodsinfo ( $currentgoodsid );

		// 品目情報が正常に取得されていた場合
		if (count ( $res ) > 0) {
			$currntgoodsname = $res ["goodsname"];

			// セッションに現在選択された品目IDと名前を入れる
			$_SESSION ["current_goodsid"] = $res ["goodsid"];
			$_SESSION ["current_goodsname"] = $res ["goodsname"];
		}

		// 予約一覧を取得
		$res = $this->Yoyakumodel->getgoodsreservation ( $currentgoodsid, $mode );
		$reservationlist = $res;

		// セッションに予約一覧を突っ込んでおく
		$_SESSION ["reservationliost"] = $res;

		// 描画のためにセット
		$this->mylib->setsmarty ( "currentgoodsid", $currentgoodsid );
		$this->mylib->setsmarty ( "currentgoodsname", $currntgoodsname );
		$this->mylib->setsmarty ( "reservationlist", $reservationlist );
		$this->mylib->setsmarty ( "userid", $userid );

		echo $this->smarty->view ( "yoyaku/part/reservation_list.html" );
	}
	function post() {

		// 送信された内容を取得
		$syear = $this->input->post ( "syear" ) ? $this->input->post ( "syear" ) : - 1;
		$smonth = $this->input->post ( "smonth" ) ? $this->input->post ( "smonth" ) : - 2;
		$sday = $this->input->post ( "sday" ) ? $this->input->post ( "sday" ) : - 3;
		$shour = $this->input->post ( "shour" ) ? $this->input->post ( "shour" ) : - 4;
		$sminute = $this->input->post ( "sminute" ) ? $this->input->post ( "sminute" ) : - 5;

		$startarray = array (
				$syear,
				$smonth,
				$sday,
				$shour,
				$sminute
		);

		$eyear = $this->input->post ( "eyear" ) ? $this->input->post ( "eyear" ) : - 11;
		$emonth = $this->input->post ( "emonth" ) ? $this->input->post ( "emonth" ) : - 12;
		$eday = $this->input->post ( "eday" ) ? $this->input->post ( "eday" ) : - 13;
		$ehour = $this->input->post ( "ehour" ) ? $this->input->post ( "ehour" ) : - 14;
		$eminute = $this->input->post ( "eminute" ) ? $this->input->post ( "eminute" ) : - 15;

		$endarray = array (
				$eyear,
				$emonth,
				$eday,
				$ehour,
				$eminute
		);

		// 自分で整形
		$start = $syear . "-" . $smonth . "-" . $sday . " " . $shour . ":" . $sminute . ":00";
		$end = $eyear . "-" . $emonth . "-" . $eday . " " . $ehour . ":" . $eminute . ":00";

		// 仮初期化
		$starttime = new DateTime ();
		$endtime = new DateTime ();

		// postされたデータが不正だとエラーを吐くので、try-catchで対処
		try {
			$starttime = new DateTime ( $start );
			$endtime = new DateTime ( $end );
		} catch ( Exception $e ) {
			log_message ( "error", "予約日時の生成に失敗しました" );
			echo "<p>選択肢が正しく選ばれていません。</p>";
			return;
		}

		// 現在の時刻を取得
		$now = new DateTime ();

		// エラーメッセージ
		unset($errormessage);

		// 開始時間が過去になっている場合
		if ($starttime < $now) {
			$errormessage = Yoyaku_c_stlib::CREATE_RESERVATION_ERROR_MESSAGE_START;
		}

		// 終了時刻が開始時刻より早い状態
		if ($starttime >= $endtime) {
			if (isset ( $errormessage )) {
				$errormessage = $errormessage . Yoyaku_c_stlib::CREATE_RESERVATION_ERROR_MESSAGE_END;
			} else {
				$errormessage = Yoyaku_c_stlib::CREATE_RESERVATION_ERROR_MESSAGE_END;
			}
		}

		// 予約重複のチェック
		if (! $this->Yoyakumodel->checkreservation ( $_SESSION ['current_goodsid'], $start, $end )) {
			if (isset ( $errormessage )) {
				$errormessage = $errormessage . Yoyaku_c_stlib::CREATE_RESERVATION_ERROR_MESSAGE_DUPLICATE;
			} else {
				$errormessage = Yoyaku_c_stlib::CREATE_RESERVATION_ERROR_MESSAGE_DUPLICATE;
			}
		}

		//一応セーフティーに動かすために
		//セッションにuseridとcurrent_goodsidが仕込まれてるかチェックする
		if(!isset( $_SESSION ["userid"], $_SESSION ['current_goodsid'])){
				if (isset ( $errormessage )) {
				$errormessage = $errormessage . "エラーが発生しました。";
			} else {
				$errormessage = "エラーが発生しました。";
			}
			log_message("error", "セッションに必要な情報が格納されていません。");
		}

		// エラーが発生してる場合
		if (isset ( $errormessage )) {
			//エラーメッセージを吐く
			echo $errormessage;
		}else{
			// 予約実行
			$res = $this->Yoyakumodel->createreservation ( $_SESSION ["userid"], $_SESSION ['current_goodsid'], $start, $end, "0" );
			//通常メッセージを吐く
			echo "予約が実行されました。";
		}


	}
	function put() {
		var_dump ( $_GET );
		var_dump ( $_POST );
		var_dump ( $this->input->raw_input_stream );

		echo "PUTが呼ばれました";
	}
	function del() {
		echo "DELが呼ばれました";
	}
}