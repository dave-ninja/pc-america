<?php

namespace App\Http\Controllers;

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

class ReportController extends Controller
{
	public function __construct() {
		$this->middleware( 'auth' );
	}

    public function index(Request $request)
    {
	    $now = Carbon::now()->format('Y-m-d');
	    $get_settings = Setting::first();
	    if( !is_null($get_settings) ) $mail = $get_settings->send_to;
	    else $mail = null;

	    $arr = [];
	    $conn = SqlSrvController::connection();

	    $lastRep = Report_detail::select('inv_number')->orderby('id','desc')->first();

	    if( !is_null($lastRep) ) $inv_number = $lastRep->inv_number;
	    else $inv_number = 0;


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
					Invoice_Itemized.Invoice_Number > $inv_number";
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

	    if( !empty($arr) ) {
		    $report_id = Report::create()->id;
		    // insert new report
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

			    Report_detail::create([
					'report_id'   => $report_id,
					'inv_number'  => $inv_number,
					'ups'         => $id,
					'title'       => $title,
					'price'       => $price,
					'qty'         => $qty,
					'discount'    => $discount,
					'total_price' => $total,
					'total_tax1'  => $tax1,
					'total_tax2'  => $tax2,
					'total_tax3'  => $tax3,
					'grand_total' => $grant_total,
					'date_time'   => $date,
				]);
		    endforeach;

		    // get new reports
		    $getAll = Report_detail::where('report_id',$report_id)->get();
		    if( $getAll ) {
			    $success = [];
			    $extID = [];
			    $f = fopen($now.'.xls','wb');
			    $list = array (
				    array('UPC', 'Title', 'Price', 'Amount Sold', 'Discount', 'Total_Price',
					    'Total_Tax1', 'Total_Tax2', 'Total_Tax3', 'Grand_Total'),
			    );
			    $xls = [];
			    foreach($getAll as $key => $item):
				    $inv_number = $item->inv_number;
				    $id = $item->ups;
				    $report_id = $item->report_id;
				    $date = $item->date_time;
				    $title = $item->title;
				    $qty = $item->qty;
				    $discount = $item->discount;
				    $price = $item->price;
				    $total = $price * $qty;
				    $tax1 = $item->total_tax1;
				    $tax2 = $item->total_tax2;
				    $tax3 = $item->total_tax3;
				    $grant_total = $total + $tax1 + $tax2 + $tax3;

				    if( !in_array($id,$extID) ) {
				        $result[$id]['ups'] = $id;
				        $result[$id]['report_id'] = $report_id;
				        $result[$id]['inv_number'] = $inv_number;
					    $result[$id]['tax1'] = $tax1;
					    $result[$id]['tax2'] = $tax2;
					    $result[$id]['tax3'] = $tax3;
					    $result[$id]['qty'] = $qty;
					    $result[$id]['discount'] = $discount;
					    $result[$id]['date'] = $date;
					    $result[$id]['title'] = $title;
					    $result[$id]['price'] = $price;
					    $result[$id]['total'] = $total;
					    $result[$id]['grant'] = $grant_total;
					    array_push($extID,$id);
				    } else {
					    $result[ $id ]['tax1']     += $tax1;
					    $result[ $id ]['tax2']     += $tax2;
					    $result[ $id ]['tax3']     += $tax3;
					    $result[ $id ]['qty']      += $qty;
					    $result[ $id ]['discount'] += $discount;
					    $result[ $id ]['total']    += $total;
					    $result[ $id ]['grant']    += $grant_total;
				    }

			    endforeach;
				$i = 0;
			    foreach($result as $key => $item) {
				    $success[$key]['ups']         = $item['ups'];
				    $success[$key]['title']       = $item['title'];
				    $success[$key]['price']       = $item['price'];
				    $success[$key]['qty']         = $item['qty'];
				    $success[$key]['discount']    = $item['discount'];
				    $success[$key]['total_price'] = $item['total'];
				    $success[$key]['total_tax1']  = $item['tax1'];
				    $success[$key]['total_tax2']  = $item['tax2'];
				    $success[$key]['total_tax3']  = $item['tax3'];
				    $success[$key]['grand_total'] = $item['grant'];
				    $success[$key]['date_time']   = $item['date'];

				    $report_id = $item['report_id'];
				    if( !in_array($report_id,$xls) ) {
					    $i++;
					    $list[$i] = [
						    0 => $item['ups'],
						    1 => $item['title'],
						    2 => $item['price'],
						    3 => $item['qty'],
						    4 => $item['discount'],
						    5 => $item['total'],
						    6 => $item['tax1'],
						    7 => $item['tax2'],
						    8 => $item['tax3'],
						    9 => $item['grant'],
					    ];
					    array_push($xls,$report_id);
				    } else {
					    $i++;
					    $list[$i] = [
						    0 => $item['ups'],
						    1 => $item['title'],
						    2 => $item['price'],
						    3 => $item['qty'],
						    4 => $item['discount'],
						    5 => $item['total'],
						    6 => $item['tax1'],
						    7 => $item['tax2'],
						    8 => $item['tax3'],
						    9 => $item['grant'],
					    ];
				    }
				    $i++;
			    } // result

			    foreach ($list as $fields) {
				    fputcsv($f, $fields, "\t", '"');
			    } fclose($f);
			    if( !is_null($mail) ) {
			        Mail::to($mail)->send(new SendMailable($success));
			        $request->session()->flash('message_success', 'Report was successful!');
			    } else {
				    $request->session()->flash('message_error', 'mail field is empty');
			    }
		    } else {
			    $request->session()->flash('message_error', 'Error');
		    }
	    } else {
		    $request->session()->flash('message_error', 'Empty Data');
	    }
	    return redirect( '/home' );
    } // function

	public function single(Request $request)
	{
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
				ORDER BY Invoice_Itemized.Invoice_Number DESC";
		$stmt = sqlsrv_query( $conn, $sql );
		$obj = sqlsrv_fetch_object($stmt);
		if( $stmt === false) {
			die( print_r( sqlsrv_errors(), true) );
		}
		$inv_number = $obj->Invoice_Number;

		if( !empty($inv_number) ) {
			$report_id = Report::create()->id;
			if($report_id) {
				// insert new report
				$ID = $obj->ItemNum;
				$title = $obj->DiffItemName;
				$qty = intval($obj->Quantity);
				$price = floatval($obj->PricePer);
				$discount = floatval($obj->Discount);
				$tax1 = floatval($obj->Tax1Per);
				$tax2 = floatval($obj->Tax2Per);
				$tax3 = floatval($obj->Tax3Per);
				$date = $obj->DateTime;

				$date = $date->format('Y-m-d');
				$total = $price * $qty;
				$tax1 = $tax1 * $qty;
				$tax2 = $tax2 * $qty;
				$tax3 = $tax3 * $qty;
				$grant_total = $total + $tax1 + $tax2 + $tax3;

				$report_detail_id = Report_detail::create([
					'report_id'   => $report_id,
					'inv_number'  => $inv_number,
					'ups'         => $ID,
					'title'       => $title,
					'price'       => $price,
					'qty'         => $qty,
					'discount'    => $discount,
					'total_price' => $total,
					'total_tax1'  => $tax1,
					'total_tax2'  => $tax2,
					'total_tax3'  => $tax3,
					'grand_total' => $grant_total,
					'date_time'   => $date,
				]);
				if($report_detail_id->report_id) {
					$upd = Setting::where('first','no')->update([
						'first' => 'yes'
					]);
					if($upd) {
						$request->session()->flash('message_success', 'Single First Report was successful!');
					}
				} else {
					$request->session()->flash('message_error', 'Error');
				}
			}
		}
		return redirect( '/home' );
	}

	public function koko()
	{
		$now = Carbon::now()->format('Y-m-d');
		$get_settings = Setting::first();
		if( !is_null($get_settings) ) {
			$mail = $get_settings->send_to;
		} else {
			$mail = null;
		}
		$arr = [];
		$conn = SqlSrvController::connection();
		$lastRep = Report_detail::select('inv_number')->orderby('id','desc')->first();

		if( !is_null($lastRep) ) {
			//$inv_number = [];
			$inv_number = $lastRep->inv_number;

		} else {
			$inv_number = 0;
		}

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
					Invoice_Itemized.Invoice_Number > $inv_number";
		$stmt = sqlsrv_query( $conn, $sql );

		if( $stmt === false) {
			die( print_r( sqlsrv_errors(), true) );
		}
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

		if( !empty($arr) ) {
			$report_id = Report::create()->id;
			// insert new report
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

				Report_detail::create([
					'report_id'   => $report_id,
					'inv_number'  => $inv_number,
					'ups'         => $id,
					'title'       => $title,
					'price'       => $price,
					'qty'         => $qty,
					'discount'    => $discount,
					'total_price' => $total,
					'total_tax1'  => $tax1,
					'total_tax2'  => $tax2,
					'total_tax3'  => $tax3,
					'grand_total' => $grant_total,
					'date_time'   => $date,
				]);
			endforeach;

			// get new reports
			$getAll = Report_detail::where('report_id',$report_id)->get();
			if( $getAll ) {
				$success = [];
				$extID = [];
				$f = fopen($now.'.xls','wb');
				$list = array (
					array('UPC', 'Title', 'Price', 'Amount Sold', 'Discount', 'Total_Price',
						'Total_Tax1', 'Total_Tax2', 'Total_Tax3', 'Grand_Total'),
				);
				$xls = [];
				foreach($getAll as $key => $item):
					$inv_number = $item->inv_number;
					$id = $item->ups;
					$report_id = $item->report_id;
					$date = $item->date_time;
					$title = $item->title;
					$qty = $item->qty;
					$discount = $item->discount;
					$price = $item->price;
					$total = $price * $qty;
					$tax1 = $item->total_tax1;
					$tax2 = $item->total_tax2;
					$tax3 = $item->total_tax3;
					$grant_total = $total + $tax1 + $tax2 + $tax3;
					if( !in_array($id,$extID) ) {
						$result[$id]['ups'] = $id;
						$result[$id]['report_id'] = $report_id;
						$result[$id]['inv_number'] = $inv_number;
						$result[$id]['tax1'] = $tax1;
						$result[$id]['tax2'] = $tax2;
						$result[$id]['tax3'] = $tax3;
						$result[$id]['qty'] = $qty;
						$result[$id]['discount'] = $discount;
						$result[$id]['date'] = $date;
						$result[$id]['title'] = $title;
						$result[$id]['price'] = $price;
						$result[$id]['total'] = $total;
						$result[$id]['grant'] = $grant_total;
						array_push($extID,$id);
					} else {
						$result[$id]['tax1'] += $tax1;
						$result[$id]['tax2'] += $tax2;
						$result[$id]['tax3'] += $tax3;
						$result[$id]['qty'] += $qty;
						$result[$id]['discount'] += $discount;
						$result[$id]['total'] += $total;
						$result[$id]['grant'] += $grant_total;
					}
				endforeach;
				$i = 0;
				foreach($result as $key => $item) {
					$success[$key]['ups']         = $item['ups'];
					$success[$key]['title']       = $item['title'];
					$success[$key]['price']       = $item['price'];
					$success[$key]['qty']         = $item['qty'];
					$success[$key]['discount']    = $item['discount'];
					$success[$key]['total_price'] = $item['total'];
					$success[$key]['total_tax1']  = $item['tax1'];
					$success[$key]['total_tax2']  = $item['tax2'];
					$success[$key]['total_tax3']  = $item['tax3'];
					$success[$key]['grand_total'] = $item['grant'];
					$success[$key]['date_time']   = $item['date'];

					$report_id = $item['report_id'];
					if( !in_array($report_id,$xls) ) {
						$i++;
						$list[$i] = [
							0 => $item['ups'],
							1 => $item['title'],
							2 => $item['price'],
							3 => $item['qty'],
							4 => $item['discount'],
							5 => $item['total'],
							6 => $item['tax1'],
							7 => $item['tax2'],
							8 => $item['tax3'],
							9 => $item['grant'],
						];
						array_push($xls,$report_id);
					} else {
						$i++;
						$list[$i] = [
							0 => $item['ups'],
							1 => $item['title'],
							2 => $item['price'],
							3 => $item['qty'],
							4 => $item['discount'],
							5 => $item['total'],
							6 => $item['tax1'],
							7 => $item['tax2'],
							8 => $item['tax3'],
							9 => $item['grant'],
						];
					}
					$i++;
				} // result

				foreach ($list as $fields) {
					fputcsv($f, $fields, "\t", '"');
				} fclose($f);
				if( !is_null($mail) ) {
					Mail::to($mail)->send(new SendMailable($success));
				} else {
					Mail::to('myninjadev@gmail.com')->send(new SendMailable($success));
				}
			}
		}
	}
}
