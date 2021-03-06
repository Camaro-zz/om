<?php
namespace App\Services;


use App\Models\OmCarType;
use App\Models\OmGoods;
use App\Models\OmGoodsCat;
use App\Models\OmGoodsImg;
use App\Models\OmGoodsMfrs;
use App\Models\OmGoodsPack;
use App\Models\OmGoodsSupplier;
use App\Models\OmOrderGoods;
use App\Models\OmSupplier;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;

class GoodsService extends BaseService {

    public function __construct(OmGoods $omGoods,SupplierService $supplierService){
        $this->uid = Auth::user()->id;
        $this->model = $omGoods;
        $this->supplierService = $supplierService;
    }

    /**
     * 添加商品
     */
    public function addGoods($data){
        $goods_data['cat_id'] = isset($data['cat_id']) ? $data['cat_id'] : 0;
        $goods = OmGoods::create($goods_data);
        if($goods->id){
            $goods->product_sn = $goods->id + 1500000;
            $goods->update();
            return ['status'=>true, 'data'=>$goods];
        }else{
            return ['status'=>false, 'msg'=>'产品添加失败'];
        }
    }

    /**
     * 添加供应商关联商品
     * @param $goods_id
     * @param $data
     */
    public function addSupplierGoods($goods_id, $data){

        $goods = OmGoods::where('id', $goods_id)->first();
        if(!$goods){
            return ['status'=>false, 'msg'=>'产品不存在'];
        }
        if(!$data['supplier_id']){
            return ['status'=>false, 'msg'=>'请选择供应商'];
        }
        $v = $this->supplierGoodsValidator($data);
        if(!$v['status']){
            return $v;
        }
        $data['goods_id'] = $goods_id;
        $data['uid'] = $this->uid;
        $res = OmGoodsSupplier::create($data);
        if($res->id){
            return ['status'=>true, 'data'=>$res];
        }else{
            return ['status'=>false, 'msg'=>'产品添加失败'];
        }
    }

    public function addGoodsMfrs($data){
        $goods_id = isset($data['goods_id']) ? intval($data['goods_id']) : 0;
        $goods = OmGoods::where('id', $goods_id)->first();
        if(!$goods){
            return ['status'=>false, 'msg'=>'产品不存在'];
        }
        $v = $this->supplierGoodsValidator($data);
        if(!$v['status']){
            return $v;
        }
        $data['uid'] = $this->uid;
        unset($data['edit']);
        unset($data['id']);
        $res = OmGoodsMfrs::create($data);
        if($res->id){
            return ['status'=>true, 'data'=>$res];
        }else{
            return ['status'=>false, 'msg'=>'生产商添加失败'];
        }
    }

    public function editGoodsMfrs($id, $data){
        $mfrs = OmGoodsMfrs::where('id',$id)->first();
        if(!$mfrs){
            return ['status'=>false,'msg'=>'记录不存在'];
        }
        $goods_id = isset($data['goods_id']) ? intval($data['goods_id']) : 0;
        $goods = OmGoods::where('id', $goods_id)->first();
        if(!$goods){
            return ['status'=>false, 'msg'=>'产品不存在'];
        }
        unset($data['edit']);
        $v = $this->supplierGoodsValidator($data);
        if(!$v['status']){
            return $v;
        }
        $res = OmGoodsMfrs::where('id',$id)->update($data);
        if($res->id){
            return ['status'=>true, 'data'=>$res];
        }else{
            return ['status'=>false, 'msg'=>'生产商更新失败'];
        }
    }

    public function editGoods($id, $data){
        $goods = OmGoods::where('id',$id)->first();
        if(!$goods){
            return ['status'=>false, 'msg'=>'产品不存在'];
        }
        $v = $this->goodsValidator($data,$id);
        if(!$v['status']){
            return $v;
        }
        $goods_data = $data;
        unset($goods_data['imgs']);
        unset($goods_data['real_imgs']);
        unset($goods_data['supplier_goods']);
        unset($goods_data['supplier_goods_count']);
        if(isset($goods_data['product_sn']))unset($goods_data['product_sn']);
        //dd($goods_data);
        $goods = OmGoods::where(array('id'=>$id,'is_deleted'=>0))->update($goods_data);

        if($goods){
            $goods = OmGoods::where('id',$id)->first();
            $goods['cat_name'] = OmGoodsCat::where('id',$goods['cat_id'])->value('name');
            return ['status'=>true, 'data'=>$goods];
        }else{
            return ['status'=>false, 'msg'=>'产品更新失败'];
        }
    }

    public function editSupplierGoods($id, $data){
        $goods = OmGoodsSupplier::where('id',$id)->first();
        if(!$goods){
            return ['status'=>false, 'msg'=>'产品不存在'];
        }
        $sup_data['name'] = isset($data['name']) ? $data['name'] : '';
        $sup_data['contacts'] = isset($data['contacts']) ? $data['contacts'] : '';
        $sup_data['tel'] = isset($data['tel']) ? $data['tel'] : '';
        $sup_data['mobile'] = isset($data['mobile']) ? $data['mobile'] : '';
        $sup_data['qq'] = isset($data['qq']) ? $data['qq'] : '';
        $goods_sup_data['mark'] = isset($data['mark']) ? $data['mark'] : '';
        $goods_sup_data['moq'] = isset($data['moq']) ? $data['moq'] : '';

        $res = $this->supplierService->supplierValidator($sup_data,$goods['supplier_id']);
        //dd($res);
        if(!$res['status']){
            return $res;
        }

        $goods_sup_data['tax_price'] = isset($data['tax_price']) ? $data['tax_price'] : 0;
        $goods_sup_data['price'] = isset($data['price']) ? $data['price'] : 0;

        $res_g = $this->supplierGoodsValidator($goods_sup_data);
        if(!$res_g['status']){
            return $res_g;
        }
        $sup = OmSupplier::where('id',$goods['supplier_id'])->update($sup_data);
        $goods = OmGoodsSupplier::where(array('id'=>$id,'is_deleted'=>0))->update($goods_sup_data);
        //dd($data);
        if($goods){
            $goods = OmGoodsSupplier::where('id',$id)->first();
            return ['status'=>true, 'data'=>$goods];
        }else{
            return ['status'=>false, 'msg'=>'更新失败'];
        }
    }

    public function getGoods($id){
        $goods = OmGoods::select('id','cat_id','product_sn','img','en_name','cn_name','mark','hs_code','tax_rate','report_key','fyi_status')->where('id',$id)->first();
        if(!$goods){
            return ['status'=>false, 'msg'=>'产品不存在'];
        }
        if($goods['cat_id'] > 0){
            $goods['cat_name'] = OmGoodsCat::where('id',$goods['cat_id'])->value('name');
        }else{
            $goods['cat_name'] = '未分类';
        }

        $goods['mfrs_sn'] = OmGoodsMfrs::where(['goods_id'=>$id,'is_deleted'=>0])->orderBy('sort','DESC')->value('mfrs_sn');
        $goods['car_type'] = OmCarType::where(['goods_id'=>$id,'is_deleted'=>0])->orderBy('sort','DESC')->select('brand','car_type')->first();
        $goods['supplier'] = OmGoodsSupplier::leftJoin('om_supplier as sup','sup.id','=','om_goods_supplier.supplier_id')
                                            ->select('sup.name','sup.supplier_sn','om_goods_supplier.price','om_goods_supplier.tax_price')
                                            ->where(['om_goods_supplier.goods_id'=>$id,'om_goods_supplier.is_deleted'=>0])
                                            ->orderBy('om_goods_supplier.sort', 'DESC')->first();
        //dd($goods);
        return ['status'=>true,'data'=>$goods];
    }

    public function getGoodsPack($goods_id){
        $pack =OmGoodsPack::where(['goods_id'=>$goods_id,'is_deleted'=>0])->select('id','num','length','width','height','gw','nw','mark','pack_type')->first();
        if(!$pack){
            $pack = array(
                'id'=>0,
                'length'=>'',
                'width'=>'',
                'height'=>'',
                'gw'=>'',
                'nw'=>'',
                'num'=>'',
                'mark'=>''
            );
        }else{
            $pack = $pack->toArray();
        }
        return $pack;
    }

    public function postGoodsPack($id,$data){
        $data['goods_id'] = $id;
        $pack = OmGoodsPack::create($data);
        if(!$pack){
            return ['status'=>false,'msg'=>'包装细节添加失败'];
        }
        return ['status'=>true,'data'=>$pack];
    }

    public function putGoodsPack($id,$data){
        $pack = OmGoodsPack::where('goods_id',$id)->update($data);
        if(!$pack){
            return ['status'=>false,'msg'=>'包装细节更新失败'];
        }
        return ['status'=>true,'data'=>$pack];
    }

    public function getGoodsXjs($id){
        $xjs = OmOrderGoods::leftJoin('om_customer as c','c.id','=','om_order_goods.customer_id')
                           ->where(['om_order_goods.goods_id'=>$id,'om_order_goods.type'=>0,'om_order_goods.is_deleted'=>0])
                           ->select('om_order_goods.*','c.name','c.customer_sn','c.contact','c.email')
                           ->get();
       return $xjs;
    }

    public function getGoodses($data){
        //$offset = isset($data['offset']) ? $data['offset'] : 0;
        $page = isset($data['page']) ? $data['page'] : 1;
        $limit = isset($data['limit']) ? $data['limit'] : 10;
        $tag_id = isset($data['tag_id']) ? $data['tag_id'] : 0;
        $list_type = isset($data['list_type']) ? $data['list_type'] : 0;
        $offset = ($page-1)*$limit;
        $query = OmGoods::leftJoin('om_goods_cat as cat','cat.id','=','om_goods.cat_id')
                        ->select('om_goods.id','om_goods.product_sn','om_goods.en_name','om_goods.cn_name','om_goods.img','cat.name as cat_name','om_goods.fyi_status','om_goods.mark')->where('om_goods.is_deleted',0);
        if(isset($data['cn_name']) && str_replace(' ','',$data['cn_name'])){
            $query->where('om_goods.cn_name', 'like', '%' . $data['cn_name'] . '%');
        }
        if(isset($data['en_name']) && str_replace(' ','',$data['en_name'])){
            $query->where('om_goods.en_name', 'like', '%' . $data['en_name'] . '%');
        }
        if(isset($data['cat_id']) && $data['cat_id'] > 0){
            $query->where('om_goods.cat_id', '=', $data['cat_id']);
        }
        if(isset($data['mfrs_sn']) && str_replace(' ','',$data['mfrs_sn'])){
            /*$data['mfrs_sn'] = str_replace('-','',$data['mfrs_sn']);
            $data['mfrs_sn'] = str_replace('.','',$data['mfrs_sn']);*/
            $data['mfrs_sn'] = str_replace('　','',$data['mfrs_sn']);
            $ids = OmGoodsMfrs::where('mfrs_sn', 'like', '%' . $data['mfrs_sn'] . '%')->lists('goods_id')->toArray();
            $ids = array_unique($ids);
            $query->whereIn('om_goods.id', $ids);
        }
        if($tag_id && $list_type==0){
            $not_in_ids = OmOrderGoods::where(['customer_id'=>$tag_id,'type'=>$list_type,'is_deleted'=>0])->lists('goods_id')->toArray();
            $query->whereNotIn('om_goods.id', $not_in_ids);
        }
        if($tag_id && $list_type==1){
            $not_in_ids = OmOrderGoods::where(['order_id'=>$tag_id,'type'=>$list_type,'is_deleted'=>0])->lists('goods_id')->toArray();
            $query->whereNotIn('om_goods.id', $not_in_ids);
        }
        $result['_count'] = $query->count();
        $result['all_page'] = ceil($result['_count'] / $limit);
        $query->skip($offset);
        $query->take($limit);
        //DB::connection()->enableQueryLog();
        $result['data'] = $query->orderBy('om_goods.created_at', 'DESC')->get();
        //dump(DB::getQueryLog());
        if ($result['data']) {
            foreach ($result['data'] as &$v) {
                $v['prop'] = OmGoodsSupplier::leftJoin('om_supplier as sup','sup.id','=','om_goods_supplier.supplier_id')
                                            ->select('sup.name','sup.supplier_sn','om_goods_supplier.*')
                                            ->where(array('om_goods_supplier.goods_id'=>$v['id'],'om_goods_supplier.is_deleted'=>0))->orderBy('sort', 'DESC')->take(3)->get();
                if(!$v['prop']){
                    $v['prop'] = '';
                }
                $v['mfrs'] = OmGoodsMfrs::select('mfrs_sn','mfrs_name','sort')->where(array('goods_id'=>$v['id'],'is_deleted'=>0))->orderBy('sort', 'DESC')->take(3)->get();
                if(!$v['mfrs']){
                    $v['mfrs'] = '';
                }
                $v['car_type'] = OmCarType::where(array('goods_id'=>$v['id'],'is_deleted'=>0))->orderBy('sort', 'DESC')->take(3)->select('brand','car_type')->get();
                if(!$v['car_type']){
                    $v['car_type'] = '';
                }

                $v['mark'] = mb_substr($v['mark'],0,10).'...';
            }
        }

        return $result;
    }

    public function deleteGoodses($ids){
        $ids = explode(',',$ids);
        $delete = OmGoods::whereIn('id',$ids)->delete();
        return ['status'=>true];
    }

    public function postMfrsGoods($id, $data){
        if(!isset($data['mfrs_sn']) || !$data['mfrs_sn']){
            return ['status'=>false,'msg'=>'原厂编号不能为空'];
        }
        if(!isset($data['mfrs_name']) || !$data['mfrs_name']){
            return ['status'=>false,'msg'=>'生产商名称不能为空'];
        }
        $data['mfrs_sn'] = $this->delSpace($data['mfrs_sn']);
        if(OmGoodsMfrs::where(['mfrs_sn'=>$data['mfrs_sn'],'is_deleted'=>0])->first()){
            return ['status'=>false,'msg'=>'原厂编号已存在'];
        }
        unset($data['id']);
        unset($data['edit']);
        $data['goods_id'] = $id;
        $data['uid'] = $this->uid;
        $mfrs = OmGoodsMfrs::create($data);
        if($mfrs){
            return ['status'=>true, 'data'=>$mfrs];
        }else{
            return ['status'=>false,'msg'=>'生产商添加失败'];
        }
    }

    //验证规则
    public function goodsValidator($data, $id=''){
        $message = [
            'en_name.required' => '英文品名不.能为空',
            'cn_name.required' => '中文品名不能为空',
            'num.integer' => '装箱数只能是整数',
            'length.numeric' => '规格长只能是数值',
            'width.numeric' => '规格宽只能是数值',
            'height.numeric' => '规格高只能是数值',
            'gw.numeric' => '毛重只能是数值',
            'nw.numeric' => '净重长只能是数值',
        ];

        $rule = [
            'en_name' => 'required',
            'cn_name' => 'required',
            'num' => 'integer',
            'length' => 'numeric',
            'width' => 'numeric',
            'height' => 'numeric',
            'gw' => 'numeric',
            'nw' => 'numeric',
        ];
        $res = $this->doValidate($data,$rule,$message);
        return $res;
    }

    //验证规则
    public function supplierGoodsValidator($data){
        $message = [
            'price.numeric' => '采购价只能为数值',
            'tax_price.numeric' => '含税采购价只能为数值',
        ];

        $rule = [
            'price' => 'numeric',
            'tax_price' => 'numeric',
        ];

        $res = $this->doValidate($data,$rule,$message);
        return $res;
    }

    public function mfrsGoodsValidator($data){
        $message = [
            'mfrs_sn.required' => '原厂编号不能为空',
            'mfrs_name.required' => '生产商名称不能为空',
        ];

        $rule = [
            'mfrs_sn' => 'required',
            'mfrs_name' => 'required',
        ];
        $res = $this->doValidate($data,$rule,$message);
        return $res;
    }

    protected function setGoodsImgs($goods, $imgs) {
        $goods->imgs()->delete();
        $imgsData = array();
        foreach ($imgs as $key => &$_img) {
            if(isset($_img['sort'])){
                if($key == 0 && $_img != $goods->img){
                    $goods->img = $_img['img'];
                }
                $img_arr['img'] = $_img['img'];
                $img_arr['sort'] = $_img['sort'];
            }else{
                if($key == 0 && $_img != $goods->img){
                    $goods->img = $_img;
                }
                $img_arr['img'] = $_img;
            }
            $goods->update();
            $imgsData[] = new OmGoodsImg($img_arr);
        }
        $goods->imgs()->saveMany($imgsData);
    }

    public function getImgs($goods_id){
        $imgs = OmGoodsImg::where(['goods_id'=>$goods_id,'is_deleted'=>0])->orderBy('sort','DESC')->lists('img');
        return $imgs;
    }

    public function getMfrsByGoods($goods_id){
        $mfrs = OmGoodsMfrs::select('id','mfrs_sn','mfrs_name','sort')->where(array('goods_id'=>intval($goods_id),'is_deleted'=>0))->orderBy('sort', 'DESC')->get();
        if(!$mfrs){
            $mfrs = '';
        }
        foreach ($mfrs as $k=>$v){
            $mfrs[$k]['edit'] = false;
        }

        return $mfrs;
    }

    public function getSuppliersByGoods($goods_id){
        $suppliers = OmGoodsSupplier::leftJoin('om_supplier as sup', 'sup.id', '=', 'om_goods_supplier.supplier_id')
                                    ->select('sup.qq','sup.tel','sup.supplier_sn','sup.name','sup.contacts','sup.mobile','om_goods_supplier.*')
                                    ->where(array('om_goods_supplier.goods_id'=>$goods_id,'om_goods_supplier.is_deleted'=>0))
                                    ->orderBy('om_goods_supplier.sort', 'DESC')->get();
        if(!$suppliers){
            $suppliers = '';
        }
        foreach ($suppliers as $k=>$v){
            $suppliers[$k]['edit'] = false;
        }

        return $suppliers;
    }
    public function getSupplierByGoods($id){
        $supplier = OmGoodsSupplier::where('id',$id)->select('goods_id','price','supplier_id','tax_price','num','length','width','height','gw','nw')->first();
        if(!$supplier || !$id){
            return ['status'=>false, 'msg'=>'参数错误'];
        }
        $goods = OmGoods::where('id',$supplier['goods_id'])->select('cn_name','en_name')->first();
        $goods_name = $goods['cn_name'].'/'.$goods['en_name'];
        return ['status'=>true, 'data'=>$supplier, 'goods_name'=>$goods_name];
    }

    public function deleteGoodsSupplier($id){
        $del = OmGoodsSupplier::where('id',$id)->delete();
        if(!$del){
            return ['status'=>false, 'msg'=>'删除失败'];
        }
        return ['status'=>true];
    }

    public function getGoodsImgs($goods_id){
        $imgs = OmGoodsImg::where(['goods_id'=>$goods_id,'is_deleted'=>0])->lists('img');
        return $imgs;
    }

    public function postGoodsImgs($goods_id,$imgs){
        //dd($imgs);
        $goods = OmGoods::where('id',$goods_id)->first();
        isset($imgs) && count($imgs)>0 && $this->setGoodsImgs($goods, $imgs);
    }

    public function postGoodsImg($goods_id,$imgs){
        $goods = OmGoods::where('id',$goods_id)->first();
        $this->setGoodsImgs($goods,$imgs);
    }

    public function deleteGoodsImg($goods_id,$img){
        OmGoodsImg::where(['goods_id'=>$goods_id,'img'=>$img])->delete();
        $first_img = OmGoodsImg::where('goods_id',$goods_id)->orderBy('sort','DESC')->first();
        if($first_img){
            OmGoods::where('id',$goods_id)->update(['img'=>$first_img['img']]);
        }
    }

    public function getGoodsCarTypes($goods_id){
        $car_types = OmCarType::where(['goods_id'=>$goods_id,'is_deleted'=>0])->orderBy('sort','DESC')->get();
        foreach ($car_types as $k=>$v){
            $car_types[$k]['edit'] = false;
        }
        return $car_types;
    }

    public function postGoodsCarType($car_type, $goods_id){
        if(!$goods_id){
            return ['status'=>false,'msg'=>'参数错误'];
        }
        if(!isset($car_type['brand']) || !$car_type['brand']){
            return ['status'=>false,'msg'=>'品牌不能为空'];
        }
        if(!isset($car_type['car_type']) || !$car_type['car_type']){
            return ['status'=>false,'msg'=>'车型不能为空'];
        }
        $car_type['goods_id'] = $goods_id;
        unset($car_type['id']);
        unset($car_type['edit']);
        $save = OmCarType::create($car_type);
        if($save){
            return ['status'=>true,'data'=>$save];
        }else{
            return ['status'=>false,'msg'=>'添加车型出错'];
        }
    }

    public function deleteGoodsCarTypes($ids){
        $res = OmCarType::whereIn('id',$ids)->delete();
        if(!$res){
            return ['status'=>false,'msg'=>'操作失败'];
        }
        return ['status'=>true];
    }

    public function putGoodsCarType($car_type, $id){
        $res = OmCarType::where(array('id'=>$id,'is_deleted'=>0))->first();
        if(!$res){
            return ['status'=>false, 'msg'=>'车型不存在'];
        }
        if(!isset($car_type['brand']) || !$car_type['brand']){
            return ['status'=>false,'msg'=>'品牌不能为空'];
        }
        if(!isset($car_type['car_type']) || !$car_type['car_type']){
            return ['status'=>false,'msg'=>'车型不能为空'];
        }

        unset($car_type['edit']);
        $car_type_id = OmCarType::where('id', $id)->update($car_type);
        if($car_type_id){
            $res = OmCarType::where('id',$car_type_id)->first();
            return ['status'=>true, 'data'=>$res];
        }else{
            return ['status'=>false, 'msg'=>'车型编辑失败'];
        }
    }

    public function sortGoodsCarType($data,$goods_id){
        foreach ($data as $k=>$v) {
            $c = explode('_',$v['id']);
            OmCarType::where(array('id'=>$c[1],'goods_id'=>$goods_id))->update(['sort'=>$v['sort']]);
        }
        $first_cartype = OmCarType::where('goods_id',$goods_id)->orderBy('sort', 'DESC')->first();
        return $first_cartype;
    }
}