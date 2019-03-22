<?php

namespace App\Http\Controllers;
use App\Mail\SearchReport;
use \Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Mail;
use App\Mail\SendMailable;
use App\Http\Controllers\SqlSrvController;
use Auth;
use App\User;
use App\Report;
use App\Report_detail;
use App\Setting;

class HomeController extends Controller
{
	public function __construct() {
		$this->middleware( 'auth' );
	}

	public function index(Request $request)
	{
		$now = Carbon::now()->format('Y-m-d');

		$get_settings = Setting::first();
		if( !is_null($get_settings) ) {
			$mail = $get_settings->send_to;
		} else {
			$mail = null;
		}

		if ( isset( $request->start_date ) && $request->start_date != null ) $start_date = $request->start_date;
		else $start_date = $now;
		if ( isset( $request->end_date ) && $request->end_date != null ) $end_date = $request->end_date;
		else $end_date = $now;

		$start = explode('-', date( "Y-m-d", strtotime( $start_date ) ) );
		$end   = explode('-', date( "Y-m-d", strtotime( $end_date ) ) );

		$arr = [];
		$conn = SqlSrvController::connection();
		$sql = "SELECT
			Invoice_Itemized.Invoice_Number,
			Invoice_Itemized.ItemNum,
			Invoice_Itemized.DiffItemName,
			Invoice_Itemized.Quantity,
			Invoice_Itemized.PricePer,
			Invoice_Totals.Discount,
			Invoice_Itemized.Tax1Per,
			Invoice_Itemized.Tax2Per,
			Invoice_Itemized.Tax3Per,
			Invoice_Totals.DateTime 
		FROM Invoice_Totals 
			LEFT JOIN Invoice_Itemized ON Invoice_Totals.Invoice_Number = Invoice_Itemized.Invoice_Number 
		WHERE 
			(DATEPART(yy, Invoice_Totals.DateTime) = $start[0] AND 
			DATEPART(mm, Invoice_Totals.DateTime) = $start[1] AND 
			DATEPART(dd, Invoice_Totals.DateTime) >= $start[2]) 
			AND 
			(DATEPART(yy, Invoice_Totals.DateTime) = $end[0] AND 
			DATEPART(mm, Invoice_Totals.DateTime) = $end[1] AND 
			DATEPART(dd, Invoice_Totals.DateTime) <= $end[2])";
		$stmt = sqlsrv_query( $conn, $sql );
		if( $stmt === false) die( print_r( sqlsrv_errors(), true) );

		$i = 0;
		while( $obj = sqlsrv_fetch_object($stmt) ) {
			$arr[$i]['inv_number'] = $obj->Invoice_Number;
			$arr[$i]['ID'] = $obj->ItemNum;
			$arr[$i]['title'] = $obj->DiffItemName;
			$arr[$i]['qty'] = floatval($obj->Quantity);
			$arr[$i]['price'] = floatval($obj->PricePer);
			$arr[$i]['discount'] = floatval($obj->Discount);
			$arr[$i]['tax1'] = floatval($obj->Tax1Per);
			$arr[$i]['tax2'] = floatval($obj->Tax2Per);
			$arr[$i]['tax3'] = floatval($obj->Tax3Per);
			$arr[$i]['date'] = $obj->DateTime;
			$i++;
		}

		$extID = [];
		if( !empty($arr) ) {
			foreach($arr as $key => $val):
				$inv_number = $val['inv_number'];
				$id = $val['ID'];
				$date = $val['date']->format('Y-m-d');
				$title = $val['title'];
				$qty = $val['qty'];
				$discount = $val['discount'];
				$price = $val['price'];
				$total = $price * $qty;
				$tax1 = $val['tax1'] * $qty;
				$tax2 = $val['tax2'] * $qty;
				$tax3 = $val['tax3'] * $qty;
				$grant_total = $total + $tax1 + $tax2 + $tax3;

				if( !in_array($id,$extID) ) {
					$result[$id]['ups'] = $inv_number;
					$result[$id]['total_tax1'] = $tax1;
					$result[$id]['total_tax2'] = $tax2;
					$result[$id]['total_tax3'] = $tax3;
					$result[$id]['qty'] = $qty;
					$result[$id]['discount'] = $discount;
					$result[$id]['date_time'] = $date;
					$result[$id]['title'] = $title;
					$result[$id]['price'] = $price;
					$result[$id]['total_price'] = $total;
					$result[$id]['grand_total'] = $grant_total;
					array_push($extID,$id);
				} else {
					$result[$id]['total_tax1'] += $tax1;
					$result[$id]['total_tax2'] += $tax2;
					$result[$id]['total_tax3'] += $tax3;
					$result[$id]['qty'] += $qty;
					$result[$id]['discount'] += $discount;
					$result[$id]['total_price'] += $total;
					$result[$id]['grand_total'] += $grant_total;
				}
			endforeach;

			if( isset($request->search_report) && $request->search_report == "Send" ) {

				if( !is_null($get_settings) ) $mail = $get_settings->send_to;
				else $mail = null;

				$f = fopen($now.'.xls','wb');
				$list = array (
					array('UPC', 'Title', 'Price', 'Amount Sold', 'Discount', 'Total_Price',
						'Total_Tax1', 'Total_Tax2', 'Total_Tax3', 'Grand_Total'),
				);
				$i = 0;

				foreach($result as $key => $item) {
					$list[$i] = [
						0 => $key,
						1 => $item['title'],
						2 => $item['price'],
						3 => $item['qty'],
						4 => $item['discount'],
						5 => $item['total_price'],
						6 => $item['total_tax1'],
						7 => $item['total_tax2'],
						8 => $item['total_tax3'],
						9 => $item['grand_total'],
					];
					$i++;
				}

				foreach ($list as $fields) {
					fputcsv($f, $fields, "\t", '"');
				} fclose($f);
				if( !is_null($mail) ) {
					Mail::to($mail)->send(new SearchReport($result));
					$request->session()->flash('message_success', 'Search Report was successful!');
				} else {
					$request->session()->flash('message_error', 'mail field is empty');
				}
			} // search report send
		} else {
			$result = false;
		}
		if ( isset( $request->exel ) ) {
			header( "Content-Type: application/xls" );
			header( "Content-Disposition: attachment; filename=report-".$start_date."-".$end_date.".xls" );
			return view( 'create.report', compact( 'start_date','end_date','result' ) );
		}

		return view('report',compact('start_date','end_date','result','mail','get_settings'));
	}

	public function mail(Request $request)
	{
		if( isset($request->set_mail_to) && $request->set_mail_to == "Set" ) {
			if( isset($request->mailto) && !empty($request->mailto) ) {
				$mailto = $request->mailto;
				if( empty($mailto) ) {
					$request->session()->flash('message_error', 'mail field is empty');
				}
				$setting = Setting::first();
				if( is_null($setting) ) {

					$ins = Setting::create([
						'send_to' => $mailto
					]);
					if($ins) {
						$request->session()->flash('message_success', 'mail created');
					}
				} else {
					$send_to = $setting->send_to;
					if( $send_to != $mailto ) {
						$upd = Setting::where('send_to',$send_to)->update([
							'send_to' => $mailto
						]);
						if($upd) {
							$request->session()->flash('message_success', 'mail updated');
						}
					} else {
						$request->session()->flash('message_success', 'old mail And new mail is Equal');
					}
				}
			}
			return redirect( '/home' );
		}
	}
}
