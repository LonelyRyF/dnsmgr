<?php

declare (strict_types=1);

namespace app\middleware;

use Closure;
use think\facade\View;
use think\Request;
use think\Response;

class ViewOutput
{
    /**
     * 处理请求
     *
     * @param Request $request
     * @param Closure $next
     * @return Response
     */
    public function handle($request, Closure $next)
    {
        View::assign('islogin', $request->islogin);
        View::assign('user', $request->user);
        View::assign('skin', getAdminSkin());
        return $next($request);
    }
}
