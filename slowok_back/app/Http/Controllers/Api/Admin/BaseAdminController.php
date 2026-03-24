<?php

namespace App\Http\Controllers\Api\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;

class BaseAdminController extends Controller
{
    /**
     * 현재 요청의 기관 ID를 가져옵니다.
     * MASTER가 헤더로 기관을 선택하지 않으면 null (전체)
     * ADMIN/TEST는 본인 기관 ID
     */
    protected function getInstitutionId(Request $request): ?int
    {
        return $request->attributes->get('institution_id');
    }
}
