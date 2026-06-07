<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;

class PolicyController extends Controller
{
    public function index()
    {
        return $this->ok([
            [
                'id' => 'shipping',
                'kicker' => 'Giao hàng',
                'title' => 'Chính sách giao hàng',
                'summary' => 'OhGiay hỗ trợ giao hàng toàn quốc với phí được tính theo địa chỉ nhận hàng.',
                'lines' => [
                    'Đơn hàng được xác nhận trước khi bàn giao vận chuyển.',
                    'Thời gian giao dự kiến 2-5 ngày làm việc tùy khu vực.',
                    'Phí giao hàng hiển thị tại bước thanh toán.',
                ],
                'lastUpdated' => '2026-06-07',
            ],
            [
                'id' => 'exchange',
                'kicker' => 'Đổi trả',
                'title' => 'Chính sách đổi trả',
                'summary' => 'Sản phẩm còn nguyên tem, hộp và chưa qua sử dụng có thể được hỗ trợ đổi size.',
                'lines' => [
                    'Liên hệ trong vòng 7 ngày kể từ khi nhận hàng.',
                    'Sản phẩm đổi trả cần đầy đủ phụ kiện và hóa đơn.',
                    'Không áp dụng đổi trả với sản phẩm đã sử dụng hoặc hư hỏng do bảo quản sai.',
                ],
                'lastUpdated' => '2026-06-07',
            ],
            [
                'id' => 'payment',
                'kicker' => 'Thanh toán',
                'title' => 'Chính sách thanh toán',
                'summary' => 'Hỗ trợ COD và các cổng thanh toán online khi được cấu hình provider.',
                'lines' => [
                    'Thanh toán COD được xác nhận khi giao hàng.',
                    'Thanh toán online chỉ hoàn tất khi backend ghi nhận trạng thái PAID.',
                    'Giao dịch bị hủy sẽ giữ đơn ở trạng thái hủy để đối soát.',
                ],
                'lastUpdated' => '2026-06-07',
            ],
        ]);
    }
}
