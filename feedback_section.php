<?php
// feedback_section.php - Khung "Phản hồi thật từ khách hàng"
?>


<!-- KHUNG PHẢN HỒI THẬT TỪ KHÁCH HÀNG -->
<div id="phan-hoi" class="real-feedback-section mt-5">
    <!-- Header của khung -->
    <div class="d-flex flex-wrap justify-content-between align-items-start mb-4 gap-3">
        <div>
            <div class="feedback-eyebrow">
                <i class="bi bi-star-fill text-warning"></i> HỌC VIÊN NÓI GÌ?
            </div>
            <h2 class="feedback-title">Phản hồi thật từ khách hàng</h2>
            <p class="feedback-subtitle">
                Những ảnh trò chuyện được lưu lại trong quá trình tư vấn, thanh toán và hỗ trợ nhận khóa học.
            </p>
        </div>
        <div>
            <a href="javascript:void(0)" onclick="openFeedbackModal('all')" class="btn-view-all-feedback">
                <span>Xem tất cả đánh giá</span>
                <i class="bi bi-arrow-right"></i>
            </a>
        </div>
    </div>

    <!-- 4 Mockup Điện thoại phản hồi thực tế -->
    <div class="row g-3 g-xl-4">
        
        <!-- CARD 01: Chungds -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="phone-feedback-card" onclick="openFeedbackModal(1)">
                <!-- Pill nhãn #01 -->
                <div class="phone-pill-badge">Phản hồi #01</div>

                <!-- Status bar -->
                <div class="phone-status-bar">
                    <span>15:29</span>
                    <div class="d-flex align-items-center gap-1">
                        <i class="bi bi-reception-4"></i>
                        <i class="bi bi-wifi"></i>
                        <i class="bi bi-battery-full text-white"></i>
                    </div>
                </div>

                <!-- Chat Header -->
                <div class="phone-chat-header">
                    <div class="chat-user-info">
                        <i class="bi bi-arrow-left text-white me-1 fs-6"></i>
                        <div class="chat-avatar" style="background: linear-gradient(135deg, #3b82f6, #1d4ed8);">C</div>
                        <div class="chat-user-text">
                            <div class="chat-username">Chungds</div>
                            <div class="chat-user-status">Đang hoạt động</div>
                        </div>
                    </div>
                    <div class="chat-actions">
                        <i class="bi bi-telephone"></i>
                        <i class="bi bi-camera-video"></i>
                        <i class="bi bi-layout-sidebar-reverse"></i>
                    </div>
                </div>

                <!-- Quick actions -->
                <div class="quick-chips-row">
                    <span class="quick-chip"><i class="bi bi-bookmark"></i> Lưu tài khoản</span>
                    <span class="quick-chip"><i class="bi bi-credit-card"></i> Chuyển khoản</span>
                </div>

                <!-- Nội dung đoạn chat -->
                <div class="chat-conversation">
                    <div class="text-center my-1">
                        <span class="badge bg-dark-subtle text-secondary" style="font-size: 0.62rem;">15:29 | 17/04/2026</span>
                    </div>

                    <div class="chat-bubble-customer">
                        Shop ơi mình chuyển khoản 599k đăng ký trọn bộ 2.000+ khóa học nhé!
                    </div>

                    <div class="chat-bubble-shop">
                        Bạn thanh toán xong gửi Bill và tài khoản gmail để xốp thêm vào khóa ạ ❤️
                    </div>

                    <!-- Bill thanh toán VCB Digibank -->
                    <div class="bill-receipt-vcb">
                        <div class="d-flex align-items-center justify-content-center gap-1 text-success mb-1">
                            <i class="bi bi-check-circle-fill fs-6"></i>
                            <span class="fw-bold" style="font-size: 0.72rem;">VCB Digibank</span>
                        </div>
                        <div class="text-muted small" style="font-size: 0.65rem;">GIAO DỊCH THÀNH CÔNG</div>
                        <div class="bill-amount text-dark">599.000 ₫</div>
                        <div class="text-secondary bill-details">
                            Đến: NGUYEN VAN THE HIEU<br>
                            ND: Chungds DK Tron bo 2000 khoa
                        </div>
                    </div>

                    <div class="chat-bubble-customer">
                        Gmail của mình: chungds.design@gmail.com. Check giúp mình nha!
                    </div>

                    <div class="chat-bubble-shop">
                        Dạ xốp đã thêm gmail của bạn vào Drive trọn bộ và mở quyền web rồi ạ. Chúc bạn học tốt nha! 🎉
                    </div>

                    <div class="chat-bubble-customer mt-auto">
                        <div class="d-flex align-items-center justify-content-between">
                            <span>Đã nhận được mail rồi shop ơi, video nét và tài liệu đầy đủ lắm ạ! Cảm ơn shop 👍</span>
                            <span class="text-info ms-1" style="font-size: 0.6rem;">✓✓</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- CARD 02: Tiendung -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="phone-feedback-card" onclick="openFeedbackModal(2)">
                <!-- Pill nhãn #02 -->
                <div class="phone-pill-badge">Phản hồi #02</div>

                <!-- Status bar -->
                <div class="phone-status-bar">
                    <span>16:45</span>
                    <div class="d-flex align-items-center gap-1">
                        <i class="bi bi-reception-4"></i>
                        <i class="bi bi-wifi"></i>
                        <i class="bi bi-battery-full text-white"></i>
                    </div>
                </div>

                <!-- Chat Header -->
                <div class="phone-chat-header">
                    <div class="chat-user-info">
                        <i class="bi bi-arrow-left text-white me-1 fs-6"></i>
                        <div class="chat-avatar" style="background: linear-gradient(135deg, #10b981, #047857);">T</div>
                        <div class="chat-user-text">
                            <div class="chat-username">Tiendung</div>
                            <div class="chat-user-status">Truy cập 2 giờ trước</div>
                        </div>
                    </div>
                    <div class="chat-actions">
                        <i class="bi bi-telephone"></i>
                        <i class="bi bi-camera-video"></i>
                        <i class="bi bi-layout-sidebar-reverse"></i>
                    </div>
                </div>

                <!-- Quick actions -->
                <div class="quick-chips-row">
                    <span class="quick-chip"><i class="bi bi-bookmark"></i> Lưu tài khoản</span>
                    <span class="quick-chip"><i class="bi bi-credit-card"></i> Chuyển khoản</span>
                </div>

                <!-- Nội dung đoạn chat -->
                <div class="chat-conversation">
                    <div class="chat-bubble-customer">
                        599k ạ
                    </div>

                    <div class="chat-bubble-shop">
                        Bạn thanh toán xong gửi Bill và tài khoản gmail để xốp thêm vào khóa ạ
                    </div>

                    <!-- Bill thanh toán Đỏ MBBank / Vietinbank -->
                    <div class="bill-receipt-red">
                        <div class="d-flex align-items-center justify-content-center gap-1 text-warning mb-1">
                            <i class="bi bi-patch-check-fill fs-6"></i>
                            <span class="fw-bold" style="font-size: 0.72rem; letter-spacing: 0.5px;">MB BANK</span>
                        </div>
                        <div class="text-white-50 small" style="font-size: 0.65rem;">GIAO DỊCH THÀNH CÔNG</div>
                        <div class="bill-amount text-warning">599,000 VND</div>
                        <div class="text-light bill-details opacity-75">
                            TK nhận: NGUYEN VAN THE HIEU<br>
                            ND: Tiendung ck khoa hoc tron doi
                        </div>
                    </div>

                    <div class="chat-bubble-customer">
                        Gửi shop bill và mail: tiendung.dev@gmail.com
                    </div>

                    <div class="chat-bubble-shop">
                        Dạ em kích hoạt thành công rồi ạ, a vào tab Thư viện khóa học xem nhé!
                    </div>

                    <div class="chat-bubble-customer mt-auto">
                        <div class="d-flex align-items-center justify-content-between">
                            <span>Ok shop, link drive mượt lắm, có cả bài thi trắc nghiệm test kiến thức xịn sò! 🔥</span>
                            <span class="text-info ms-1" style="font-size: 0.6rem;">✓✓</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- CARD 03: Ngọc Lê -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="phone-feedback-card" onclick="openFeedbackModal(3)">
                <!-- Pill nhãn #03 -->
                <div class="phone-pill-badge">Phản hồi #03</div>

                <!-- Status bar -->
                <div class="phone-status-bar">
                    <span>12:14</span>
                    <div class="d-flex align-items-center gap-1">
                        <i class="bi bi-reception-4"></i>
                        <i class="bi bi-wifi"></i>
                        <i class="bi bi-battery-half text-white"></i>
                    </div>
                </div>

                <!-- Chat Header -->
                <div class="phone-chat-header">
                    <div class="chat-user-info">
                        <i class="bi bi-arrow-left text-white me-1 fs-6"></i>
                        <div class="chat-avatar" style="background: linear-gradient(135deg, #ec4899, #be185d);">N</div>
                        <div class="chat-user-text">
                            <div class="chat-username">Ngọc Lê</div>
                            <span class="badge bg-secondary-subtle text-secondary" style="font-size: 0.58rem; padding: 2px 5px;">Người liên hệ</span>
                        </div>
                    </div>
                    <div class="chat-actions">
                        <i class="bi bi-person-plus text-primary"></i>
                        <i class="bi bi-telephone"></i>
                        <i class="bi bi-layout-sidebar-reverse"></i>
                    </div>
                </div>

                <!-- Quick actions -->
                <div class="quick-chips-row">
                    <span class="quick-chip"><i class="bi bi-bookmark"></i> Lưu tài khoản</span>
                    <span class="quick-chip"><i class="bi bi-credit-card"></i> Chuyển khoản</span>
                </div>

                <!-- Nội dung đoạn chat -->
                <div class="chat-conversation">
                    <div class="chat-bubble-customer">
                        Khóa học Marketing với Ngoại ngữ có được cập nhật thường xuyên không ạ?
                    </div>

                    <div class="chat-bubble-shop">
                        Dạ có bạn nhé! Gói 599k được cập nhật video mới hàng tuần hoàn toàn miễn phí trọn đời ạ.
                    </div>

                    <div class="chat-bubble-customer">
                        Dạ vậy mình lấy gói 599k luôn nha, vừa ck xong ạ.
                    </div>

                    <!-- Bill thanh toán VCB Digibank -->
                    <div class="bill-receipt-vcb">
                        <div class="d-flex align-items-center justify-content-center gap-1 text-success mb-1">
                            <i class="bi bi-check-circle-fill fs-6"></i>
                            <span class="fw-bold" style="font-size: 0.72rem;">VCB Digibank</span>
                        </div>
                        <div class="text-muted small" style="font-size: 0.65rem;">GIAO DỊCH THÀNH CÔNG</div>
                        <div class="bill-amount text-dark">599.000 ₫</div>
                        <div class="text-secondary bill-details">
                            Đến: NGUYEN VAN THE HIEU<br>
                            ND: Ngoc Le DK Khoa Hoc VIP
                        </div>
                    </div>

                    <div class="chat-bubble-shop">
                        Dạ xốp đã gửi link Drive trọn bộ và hướng dẫn làm bài trắc nghiệm vào mail ngocle@gmail.com rồi ạ!
                    </div>

                    <div class="chat-bubble-customer mt-auto">
                        <div class="d-flex align-items-center justify-content-between">
                            <span>Cảm ơn shop nhiều nha, kích hoạt siêu nhanh chưa tới 1 phút là có rồi ❤️</span>
                            <span class="text-info ms-1" style="font-size: 0.6rem;">✓✓</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <!-- CARD 04: Quỳnh Như -->
        <div class="col-12 col-sm-6 col-lg-3">
            <div class="phone-feedback-card" onclick="openFeedbackModal(4)">
                <!-- Pill nhãn #04 -->
                <div class="phone-pill-badge">Phản hồi #04</div>

                <!-- Status bar -->
                <div class="phone-status-bar">
                    <span>18:20</span>
                    <div class="d-flex align-items-center gap-1">
                        <i class="bi bi-reception-4"></i>
                        <i class="bi bi-wifi"></i>
                        <i class="bi bi-battery-full text-white"></i>
                    </div>
                </div>

                <!-- Chat Header -->
                <div class="phone-chat-header">
                    <div class="chat-user-info">
                        <i class="bi bi-arrow-left text-white me-1 fs-6"></i>
                        <div class="chat-avatar" style="background: linear-gradient(135deg, #f59e0b, #b45309);">Q</div>
                        <div class="chat-user-text">
                            <div class="chat-username">Quỳnh Như 03 MIL..</div>
                            <div class="chat-user-status">Vừa mới truy cập</div>
                        </div>
                    </div>
                    <div class="chat-actions">
                        <i class="bi bi-telephone"></i>
                        <i class="bi bi-camera-video"></i>
                        <i class="bi bi-layout-sidebar-reverse"></i>
                    </div>
                </div>

                <!-- Quick actions -->
                <div class="quick-chips-row">
                    <span class="quick-chip"><i class="bi bi-credit-card"></i> Chuyển khoản</span>
                </div>

                <!-- Nội dung đoạn chat -->
                <div class="chat-conversation">
                    <div class="chat-bubble-customer">
                        599k ạ
                    </div>

                    <div class="chat-bubble-shop">
                        Bạn thanh toán xong gửi Bill và tài khoản gmail để xốp thêm vào khóa ạ
                    </div>

                    <!-- Bill thanh toán VCB Digibank -->
                    <div class="bill-receipt-vcb">
                        <div class="d-flex align-items-center justify-content-center gap-1 text-success mb-1">
                            <i class="bi bi-check-circle-fill fs-6"></i>
                            <span class="fw-bold" style="font-size: 0.72rem;">VCB Digibank</span>
                        </div>
                        <div class="text-muted small" style="font-size: 0.65rem;">GIAO DỊCH THÀNH CÔNG</div>
                        <div class="bill-amount text-dark">599.000 ₫</div>
                        <div class="text-secondary bill-details">
                            Đến: NGUYEN VAN THE HIEU<br>
                            ND: Quynh Nhu ck khoa hoc
                        </div>
                    </div>

                    <div class="chat-bubble-customer">
                        Mail em là quynhnhu.marketing@gmail.com nha shop
                    </div>

                    <div class="chat-bubble-shop">
                        Dạ xốp đã thêm bạn vào khóa rồi nha, bạn đăng nhập web là học được ngay ạ!
                    </div>

                    <div class="chat-bubble-customer mt-auto">
                        <div class="d-flex align-items-center justify-content-between">
                            <span>Khóa học chất lượng lắm ạ, bạn bè mình ai cần mình sẽ giới thiệu ủng hộ shop nha! ⭐⭐⭐⭐⭐</span>
                            <span class="text-info ms-1" style="font-size: 0.6rem;">✓✓</span>
                        </div>
                    </div>
                </div>
            </div>
        </div>

    </div>

    <!-- Thanh footer dưới cùng của Section -->
    <div class="feedback-bottom-bar">
        <div class="d-flex align-items-center text-secondary small">
            <i class="bi bi-patch-check-fill text-warning fs-5 me-2"></i>
            <span class="text-light opacity-75">Phản hồi nguyên bản từ cộng đồng học viên</span>
        </div>
        <div>
            <a href="javascript:void(0)" onclick="openFeedbackModal('all')" class="feedback-link-gold">
                Xem toàn bộ 60+ phản hồi
                <i class="bi bi-chevron-right"></i>
            </a>
        </div>
    </div>
</div>

<!-- MODAL XEM CHI TIẾT ĐÁNH GIÁ & TẤT CẢ PHẢN HỒI -->
<div class="modal fade feedback-modal" id="allFeedbackModal" tabindex="-1" aria-labelledby="allFeedbackModalLabel" aria-hidden="true">
    <div class="modal-dialog modal-lg modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content shadow-lg">
            <div class="modal-header border-bottom border-secondary border-opacity-25 pb-3">
                <div>
                    <h5 class="modal-title fw-bold text-white d-flex align-items-center gap-2" id="allFeedbackModalLabel">
                        <i class="bi bi-chat-heart-fill text-warning"></i>
                        Đánh giá & Phản hồi thực tế từ học viên
                    </h5>
                    <p class="text-secondary small mb-0 mt-1">
                        Tổng hợp hơn 1.250+ đánh giá 5 sao từ học viên đã tham gia học tập trên hệ thống
                    </p>
                </div>
                <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-4">
                <!-- Thống kê đánh giá -->
                <div class="p-3 rounded-4 mb-4" style="background: rgba(255,255,255,0.04); border: 1px solid rgba(255,255,255,0.08);">
                    <div class="row align-items-center text-center text-md-start g-3">
                        <div class="col-md-4 text-center border-end-md border-secondary border-opacity-25">
                            <div class="display-5 fw-bold text-warning mb-1">4.9 / 5</div>
                            <div class="text-warning small mb-1">
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                                <i class="bi bi-star-fill"></i>
                            </div>
                            <div class="text-secondary small">Dựa trên 1.250+ học viên</div>
                        </div>
                        <div class="col-md-8">
                            <div class="d-flex align-items-center gap-2 mb-2">
                                <span class="badge bg-success-subtle text-success"><i class="bi bi-shield-check"></i> 100% Uy tín</span>
                                <span class="badge bg-primary-subtle text-primary"><i class="bi bi-lightning-fill"></i> Kích hoạt dưới 30s</span>
                                <span class="badge bg-warning-subtle text-warning-emphasis"><i class="bi bi-infinity"></i> Học trọn đời</span>
                            </div>
                            <p class="text-secondary small mb-0">
                                Mọi hình ảnh tin nhắn, bill chuyển khoản đều là giao dịch thật 100% qua Zalo và Fanpage hỗ trợ chính thức.
                            </p>
                        </div>
                    </div>
                </div>

                <!-- Danh sách các phản hồi nổi bật -->
                <h6 class="fw-bold text-light mb-3">Các phản hồi mới nhất:</h6>
                <div class="row g-3">
                    <!-- Item 1 -->
                    <div class="col-md-6">
                        <div class="p-3 rounded-3 h-100" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.07);">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="chat-avatar" style="background: #3b82f6;">C</div>
                                    <div>
                                        <div class="fw-bold text-light small">Chungds (Thiết kế đồ họa)</div>
                                        <div class="text-secondary" style="font-size: 0.7rem;">Gói: Trọn bộ 2.000+ khóa học</div>
                                    </div>
                                </div>
                                <span class="badge bg-success">599.000 ₫</span>
                            </div>
                            <div class="text-warning small mb-2"><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i></div>
                            <p class="text-light opacity-75 small mb-0">
                                "Đã nhận được mail rồi shop ơi, video nét và tài liệu đầy đủ lắm ạ! Cảm ơn shop, gói này quá hời so với mua lẻ từng khóa."
                            </p>
                        </div>
                    </div>

                    <!-- Item 2 -->
                    <div class="col-md-6">
                        <div class="p-3 rounded-3 h-100" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.07);">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="chat-avatar" style="background: #10b981;">T</div>
                                    <div>
                                        <div class="fw-bold text-light small">Tiendung (Lập trình viên)</div>
                                        <div class="text-secondary" style="font-size: 0.7rem;">Gói: Trọn bộ VIP 599K</div>
                                    </div>
                                </div>
                                <span class="badge bg-success">599.000 ₫</span>
                            </div>
                            <div class="text-warning small mb-2"><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i></div>
                            <p class="text-light opacity-75 small mb-0">
                                "Ok shop, link drive mượt lắm, có cả bài thi trắc nghiệm test kiến thức xịn sò! Học xong thực hành được ngay trên dự án."
                            </p>
                        </div>
                    </div>

                    <!-- Item 3 -->
                    <div class="col-md-6">
                        <div class="p-3 rounded-3 h-100" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.07);">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="chat-avatar" style="background: #ec4899;">N</div>
                                    <div>
                                        <div class="fw-bold text-light small">Ngọc Lê (Marketing Specialist)</div>
                                        <div class="text-secondary" style="font-size: 0.7rem;">Gói: Trọn bộ Marketing & Ngoại ngữ</div>
                                    </div>
                                </div>
                                <span class="badge bg-success">599.000 ₫</span>
                            </div>
                            <div class="text-warning small mb-2"><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i></div>
                            <p class="text-light opacity-75 small mb-0">
                                "Cảm ơn shop nhiều nha, kích hoạt siêu nhanh chưa tới 1 phút là có rồi. Đội ngũ support Zalo rất nhiệt tình, nhắn là trả lời liền."
                            </p>
                        </div>
                    </div>

                    <!-- Item 4 -->
                    <div class="col-md-6">
                        <div class="p-3 rounded-3 h-100" style="background: rgba(255,255,255,0.03); border: 1px solid rgba(255,255,255,0.07);">
                            <div class="d-flex justify-content-between align-items-center mb-2">
                                <div class="d-flex align-items-center gap-2">
                                    <div class="chat-avatar" style="background: #f59e0b;">Q</div>
                                    <div>
                                        <div class="fw-bold text-light small">Quỳnh Như (Nội dung số & MMO)</div>
                                        <div class="text-secondary" style="font-size: 0.7rem;">Gói: Hội viên Trọn đời</div>
                                    </div>
                                </div>
                                <span class="badge bg-success">599.000 ₫</span>
                            </div>
                            <div class="text-warning small mb-2"><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i><i class="bi bi-star-fill"></i></div>
                            <p class="text-light opacity-75 small mb-0">
                                "Khóa học chất lượng lắm ạ, bạn bè mình ai cần mình sẽ giới thiệu ủng hộ shop nha! 10/10 điểm cho chất lượng và độ uy tín."
                            </p>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-footer border-top border-secondary border-opacity-25">
                <a href="index.php#goi-hoi-vien" class="btn btn-warning fw-semibold px-4 rounded-pill">
                    Đăng ký gói 599K ngay hôm nay
                </a>
                <button type="button" class="btn btn-secondary rounded-pill px-3" data-bs-dismiss="modal">Đóng</button>
            </div>
        </div>
    </div>
</div>

<script>
function openFeedbackModal(item) {
    const modalEl = document.getElementById('allFeedbackModal');
    if (modalEl && typeof bootstrap !== 'undefined') {
        const modal = new bootstrap.Modal(modalEl);
        modal.show();
    }
}
</script>
