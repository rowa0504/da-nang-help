import { LegalContent } from './types';

const content: LegalContent = {
    terms: {
        title: 'Điều Khoản Dịch Vụ (Bản thảo)',
        sections: [
            {
                heading: '1. Phạm vi áp dụng',
                blocks: [
                    {
                        type: 'p',
                        text:
                            'Điều khoản Dịch vụ này ("Điều khoản") quy định việc sử dụng nền tảng kết nối Da Nang Help ("Dịch vụ") do {{operator_name}} ("Đơn vị vận hành") vận hành. Khi đăng ký, bạn sẽ được yêu cầu xác nhận rõ ràng việc đồng ý với Điều khoản này bằng một ô chọn (checkbox). Khi hoàn tất đăng ký, bạn được xem là đã đồng ý với Điều khoản này.',
                    },
                ],
            },
            {
                heading: '2. Nội dung Dịch vụ',
                blocks: [
                    {
                        type: 'p',
                        text:
                            'Dịch vụ là nền tảng kết nối Customer tìm kiếm dịch vụ tại Đà Nẵng ("Customer") với Provider cung cấp dịch vụ trên nền tảng ("Provider"). Đơn vị vận hành chỉ cung cấp nơi để Customer và Provider kết nối với nhau, và không phải là một bên trong giao dịch hoặc dịch vụ thực tế được thực hiện giữa họ. Dịch vụ không cung cấp chức năng thanh toán hoặc ký quỹ (escrow); việc thanh toán được thực hiện trực tiếp giữa Customer và Provider. Đơn vị vận hành không bảo đảm rằng giá được đăng phản ánh đúng điều kiện thực tế đã thỏa thuận giữa hai bên.',
                    },
                ],
            },
            {
                heading: '3. Điều kiện sử dụng',
                blocks: [
                    {
                        type: 'p',
                        text: 'Dịch vụ chỉ dành cho người từ 18 tuổi trở lên. Bạn sẽ được yêu cầu xác nhận đã đủ 18 tuổi khi đăng ký.',
                    },
                ],
            },
            {
                heading: '4. Hành vi bị cấm',
                blocks: [
                    { type: 'p', text: 'Khi sử dụng Dịch vụ, bạn không được:' },
                    {
                        type: 'ul',
                        items: [
                            'vi phạm pháp luật áp dụng, hoặc cung cấp/môi giới dịch vụ, hàng hóa bất hợp pháp;',
                            'xâm phạm quyền của người khác (quyền sở hữu trí tuệ, quyền riêng tư, danh dự, v.v.);',
                            'đăng ký hoặc đăng tải thông tin sai sự thật;',
                            'gây tải quá mức lên hệ thống của Dịch vụ, hoặc truy cập trái phép;',
                            'đăng tải các dịch vụ mà việc cung cấp/môi giới bị pháp luật Việt Nam hạn chế;',
                            'thực hiện bất kỳ hành vi nào khác mà Đơn vị vận hành cho là không phù hợp.',
                        ],
                    },
                ],
            },
            {
                heading: '5. Nội dung của bạn và quyền sử dụng được cấp',
                blocks: [
                    {
                        type: 'p',
                        text:
                            'Bạn hoàn toàn chịu trách nhiệm về nội dung bạn đăng (Yêu cầu dịch vụ, Offer, Đánh giá và nội dung khác, gọi chung là "Nội dung người dùng"). Khi đăng Nội dung người dùng, bạn cấp cho Đơn vị vận hành quyền sử dụng miễn phí, không độc quyền, trong phạm vi cần thiết để vận hành Dịch vụ, bao gồm hiển thị, kiểm duyệt và dịch máy.',
                    },
                ],
            },
            {
                heading: '6. Kiểm duyệt',
                blocks: [
                    {
                        type: 'p',
                        text:
                            'Đơn vị vận hành có thể ẩn Nội dung người dùng, hoặc thu hồi/tạm ngưng xác thực của Provider mà không cần thông báo trước, nếu vi phạm Điều khoản này hoặc pháp luật áp dụng.',
                    },
                ],
            },
            {
                heading: '7. Về dịch máy',
                blocks: [
                    {
                        type: 'p',
                        text:
                            'Nội dung trên Dịch vụ có thể được hiển thị bằng nhiều ngôn ngữ thông qua dịch máy bằng Amazon Translate. Kết quả dịch máy có thể không chính xác, và Đơn vị vận hành không bảo đảm độ chính xác. Đối với nội dung quan trọng, vui lòng kiểm tra bản gốc hoặc liên hệ trực tiếp để xác nhận.',
                    },
                ],
            },
            {
                heading: '8. Không phải dịch vụ khẩn cấp',
                blocks: [
                    {
                        type: 'p',
                        text:
                            'Dịch vụ không nhằm mục đích ứng cứu khẩn cấp hoặc dịch vụ y tế cấp cứu. Trong trường hợp khẩn cấp liên quan đến tính mạng, sức khỏe hoặc tài sản, vui lòng không sử dụng Dịch vụ — hãy liên hệ trực tiếp với cơ quan khẩn cấp phù hợp.',
                    },
                ],
            },
            {
                heading: '9. Miễn trừ trách nhiệm và giới hạn trách nhiệm',
                blocks: [
                    { type: 'p', text: 'Trong phạm vi pháp luật áp dụng cho phép, Đơn vị vận hành không chịu trách nhiệm đối với:' },
                    {
                        type: 'ul',
                        items: [
                            'dịch vụ hoặc giao dịch thực tế được thực hiện giữa Customer và Provider, bao gồm nội dung, chất lượng hoặc kết quả;',
                            'tính chính xác hoặc hợp pháp của Nội dung người dùng;',
                            'độ chính xác của kết quả dịch máy;',
                            'bất kỳ thiệt hại nào phát sinh từ việc gián đoạn, ngừng cung cấp Dịch vụ, hoặc mất dữ liệu.',
                        ],
                    },
                    {
                        type: 'p',
                        text:
                            'Trường hợp Đơn vị vận hành vẫn phải chịu trách nhiệm, trách nhiệm đó chỉ giới hạn trong phạm vi pháp luật áp dụng cho phép.',
                    },
                ],
            },
            {
                heading: '10. Luật áp dụng và giải quyết tranh chấp',
                blocks: [
                    {
                        type: 'p',
                        text:
                            'Điều khoản này được điều chỉnh theo pháp luật Việt Nam. Thủ tục và cơ quan giải quyết tranh chấp cụ thể sẽ được xác định, sau khi có sự xem xét của chuyên gia pháp lý, trước khi Dịch vụ được công khai cho người dùng thực tế.',
                    },
                ],
            },
            {
                heading: '11. Thay đổi Điều khoản',
                blocks: [
                    {
                        type: 'p',
                        text:
                            'Khi Đơn vị vận hành sửa đổi Điều khoản này, Đơn vị vận hành sẽ thông báo trước nội dung thay đổi và ngày có hiệu lực, qua Dịch vụ hoặc phương thức phù hợp khác, với thời gian thông báo hợp lý. Đối với các thay đổi ảnh hưởng quan trọng đến người dùng, Đơn vị vận hành sẽ xin lại sự đồng ý theo yêu cầu của pháp luật áp dụng.',
                    },
                ],
            },
            {
                heading: '12. Ngôn ngữ ưu tiên và liên hệ',
                blocks: [
                    {
                        type: 'p',
                        text:
                            'Điều khoản này được cung cấp bằng tiếng Anh, tiếng Nhật và tiếng Việt. Nếu có sự khác biệt giữa các phiên bản, phiên bản {{governing_locale_language}} sẽ được ưu tiên áp dụng. Mọi thắc mắc về Điều khoản này, vui lòng liên hệ {{contact_email}}.',
                    },
                ],
            },
        ],
    },
    privacy: {
        title: 'Chính Sách Quyền Riêng Tư (Bản thảo)',
        sections: [
            {
                heading: '1. Thông tin thu thập',
                blocks: [
                    {
                        type: 'ul',
                        items: [
                            'Thông tin tài khoản: họ tên, địa chỉ email, mật khẩu (lưu dưới dạng mã hóa băm; Đơn vị vận hành không thể xem mật khẩu dạng văn bản thuần), vai trò, ngôn ngữ hiển thị, số điện thoại (không bắt buộc)',
                            'Thông tin Provider: tên doanh nghiệp, giới thiệu bản thân, danh mục dịch vụ, khu vực hoạt động, trạng thái và lịch sử xét duyệt',
                            'Nội dung bạn đăng: nội dung Yêu cầu dịch vụ (tiêu đề, mô tả, danh mục, khu vực, địa chỉ, mức độ khẩn cấp, hình ảnh), nội dung Offer (giá, lời nhắn, thời gian có thể thực hiện), nội dung Job, nội dung đánh giá (điểm số, bình luận)',
                            'Thông tin thông báo: loại, nội dung (dạng JSON), trạng thái đã đọc, và thời gian của thông báo trong ứng dụng. Các thông báo này có thể kích hoạt gửi email, nhưng chúng tôi không lưu nhật ký riêng về việc email đó có được gửi thành công hay không.',
                            'Thông tin truy cập: địa chỉ IP, dữ liệu phiên (session), nhật ký truy cập và lỗi',
                            'Dữ liệu ghi nhận: thời gian tạo/cập nhật, lịch sử kiểm duyệt',
                        ],
                    },
                ],
            },
            {
                heading: '2. Mục đích sử dụng và thời hạn lưu trữ',
                blocks: [
                    {
                        type: 'p',
                        text:
                            'Chúng tôi sử dụng thông tin trên để vận hành Dịch vụ, xác thực và quản lý tài khoản, ngăn chặn lạm dụng, hỗ trợ khách hàng, và tuân thủ các nghĩa vụ pháp lý. Chúng tôi lưu trữ thông tin trong thời gian cần thiết cho các mục đích trên hoặc theo thời hạn pháp luật quy định, xem xét theo từng trường hợp. Xem Mục 7 về yêu cầu xóa dữ liệu.',
                    },
                ],
            },
            {
                heading: '3. Chia sẻ với bên thứ ba / bên xử lý',
                blocks: [
                    {
                        type: 'ul',
                        items: [
                            'Amazon Translate (AWS): dùng để dịch máy tiêu đề/mô tả của Yêu cầu dịch vụ và lời nhắn của Offer. Việc xử lý chủ yếu diễn ra tại khu vực Singapore; tuy nhiên, AWS có thể xử lý hoặc lưu trữ nội dung tại các khu vực khác theo hoạt động vận hành của họ. AWS có thể lưu trữ và sử dụng nội dung đầu vào để duy trì, cải thiện dịch vụ, và để cải thiện, phát triển công nghệ học máy và AI của mình. Chúng tôi không bảo đảm độ chính xác của bản dịch. Vui lòng không ghi số điện thoại, địa chỉ chính xác, hoặc thông tin cá nhân không cần thiết khác trong nội dung Yêu cầu dịch vụ hoặc Offer.',
                            'Amazon S3 (AWS): lưu trữ hình ảnh được tải lên (đã xóa EXIF, lưu ở chế độ riêng tư, hiển thị qua URL có chữ ký, có thời hạn)',
                            'Amazon RDS (AWS): lưu trữ cơ sở dữ liệu',
                            'Amazon ECS/Fargate (AWS): nền tảng vận hành ứng dụng',
                            'Amazon SES (AWS): gửi email thông báo',
                            'Amazon CloudWatch (AWS): thu thập nhật ký ứng dụng và lỗi',
                        ],
                    },
                    {
                        type: 'p',
                        text:
                            'Chúng tôi không bán dữ liệu cá nhân. Chúng tôi có thể tiết lộ dữ liệu cá nhân trong phạm vi cần thiết theo yêu cầu hợp pháp.',
                    },
                ],
            },
            {
                heading: '4. Tiết lộ giữa người dùng',
                blocks: [
                    {
                        type: 'p',
                        text:
                            'Provider đã được xét duyệt có thể xem Yêu cầu dịch vụ đang ở trạng thái mở (open) và hiển thị (visible); tuy nhiên, các trường thông tin riêng tư như địa chỉ chính xác và số điện thoại sẽ không được hiển thị cho đến khi Job được hình thành. Mức độ phù hợp giữa danh mục/khu vực của Provider với yêu cầu chỉ ảnh hưởng đến thứ tự xếp hạng trong feed và việc có xuất hiện trong bộ lọc "đề xuất" hay không — không hạn chế việc Provider xem yêu cầu hay gửi Offer. Khi Provider gửi Offer, nội dung Offer đó (giá, lời nhắn) cùng với tên doanh nghiệp, điểm đánh giá trung bình, số lượng công việc hoàn thành của Provider đó (và chi tiết danh mục "khác" nếu có) sẽ được hiển thị cho Customer đã đăng yêu cầu. Phần giới thiệu bản thân và danh sách đầy đủ danh mục/khu vực đã đăng ký của Provider chỉ hiển thị cho chính Provider đó và Admin. Nội dung đánh giá (điểm số và bình luận) chỉ hiển thị cho Customer và Provider là các bên liên quan đến Job đó, và cho Admin. Địa chỉ chính xác và số điện thoại của Yêu cầu dịch vụ không được tiết lộ cho đến khi Job được hình thành (Offer được chấp nhận); sau đó chỉ được tiết lộ giữa Customer và Provider liên quan. Admin có thể xem các thông tin này nhằm mục đích xét duyệt và kiểm duyệt.',
                    },
                ],
            },
            {
                heading: '5. Chuyển dữ liệu qua biên giới',
                blocks: [
                    {
                        type: 'p',
                        text:
                            'Hạ tầng của Dịch vụ vận hành trên AWS tại khu vực Singapore. Do đó, dữ liệu cá nhân được thu thập tại Việt Nam sẽ được xử lý và lưu trữ ngoài lãnh thổ Việt Nam, cấu thành "chuyển dữ liệu cá nhân qua biên giới" theo Luật Bảo vệ dữ liệu cá nhân (Luật số 91/2025/QH15) và Nghị định số 356/2025/NĐ-CP. Chúng tôi sẽ tuân thủ các thủ tục pháp luật yêu cầu đối với việc chuyển dữ liệu này.',
                    },
                ],
            },
            {
                heading: '6. Cookie',
                blocks: [
                    {
                        type: 'p',
                        text:
                            'Chúng tôi chỉ sử dụng cookie thiết yếu (cookie phiên) cần thiết để vận hành Dịch vụ, ví dụ để duy trì trạng thái đăng nhập. Chúng tôi không sử dụng cookie theo dõi cho mục đích quảng cáo.',
                    },
                ],
            },
            {
                heading: '7. Quyền của bạn',
                blocks: [
                    {
                        type: 'p',
                        text:
                            'Theo quy định pháp luật áp dụng, bạn có quyền yêu cầu truy cập, chỉnh sửa, xóa, hạn chế sử dụng dữ liệu cá nhân của mình, hoặc rút lại sự đồng ý. Dịch vụ hiện chưa có chức năng tự xóa tài khoản; vui lòng liên hệ theo thông tin tại Mục 10 để gửi yêu cầu. Chúng tôi có thể không đáp ứng đầy đủ yêu cầu của bạn trong trường hợp pháp luật yêu cầu lưu trữ, nhằm ngăn chặn gian lận, hoặc để giải quyết tranh chấp.',
                    },
                ],
            },
            {
                heading: '8. Người chưa đủ tuổi',
                blocks: [
                    {
                        type: 'p',
                        text: 'Dịch vụ không dành cho người dưới 18 tuổi. Chúng tôi yêu cầu xác nhận bạn đã đủ 18 tuổi khi đăng ký.',
                    },
                ],
            },
            {
                heading: '9. Thay đổi Chính sách',
                blocks: [
                    {
                        type: 'p',
                        text:
                            'Khi Đơn vị vận hành sửa đổi Chính sách này, Đơn vị vận hành sẽ thông báo trước nội dung thay đổi và ngày có hiệu lực, qua Dịch vụ hoặc phương thức phù hợp khác, với thời gian thông báo hợp lý. Đối với các thay đổi ảnh hưởng quan trọng đến người dùng, Đơn vị vận hành sẽ xin lại sự đồng ý theo yêu cầu của pháp luật áp dụng.',
                    },
                ],
            },
            {
                heading: '10. Ngôn ngữ ưu tiên và liên hệ',
                blocks: [
                    {
                        type: 'p',
                        text:
                            'Chính sách này được cung cấp bằng tiếng Anh, tiếng Nhật và tiếng Việt. Nếu có sự khác biệt giữa các phiên bản, phiên bản {{governing_locale_language}} sẽ được ưu tiên áp dụng. Mọi thắc mắc về Chính sách này, vui lòng liên hệ {{contact_email}}.',
                    },
                ],
            },
        ],
    },
};

export default content;
