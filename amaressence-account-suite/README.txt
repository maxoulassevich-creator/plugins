Amaressence Account Suite 2.1.5

Шорткоды:
[ama_auth_form]
[ama_customer_dashboard]
[relod_auth_form]
[relod_customer_dashboard]

Опционально для формы:
[ama_auth_form user_agreement_url="https://amaressence.ru/informacziya/#soglasie-s-obrabotkoj-pdn" loyalty_url="https://amaressence.ru/informacziya/#programma-loyalnosti"]

Опционально для кабинета:
[ama_customer_dashboard auth_page_url="https://site.ru/login/"]

Интеграция с RELOD Referral Points:
- баланс баллов в кабинете берётся из фильтра ama_account_suite_points_balance, затем из таблицы rrp_profiles, затем из пользовательских meta-полей;
- личная реферальная ссылка показывается на главной вкладке кабинета, если найден профиль в rrp_profiles с referral_code;
- если профиль не найден, показывается сообщение о необходимости оформить первый заказ.

Обновление 2.1.5:
- кнопка «Отменить заказ» в разделе заказов личного кабинета учитывает статусы Яндекс.Доставки;
- кнопка пропадает, как только статус Яндекс.Доставки стал SORTING_CENTER_AT_START / «Поступил на приём», а также на последующих, финальных и возвратных статусах;
- если заказ уже экспортирован в Яндекс.Доставку, отмена из кабинета сначала вызывает cancel_action() основного плагина Яндекс.Доставки;
- после успешной отмены заказ переводится в статус WooCommerce «Отменён»;
- администратору отправляется HTML-письмо в стиле Amaressence о том, что покупатель отменил заказ из личного кабинета;
- получатели письма берутся из стандартной настройки WooCommerce → Настройки → Email-ы → Новый заказ. Если там получатель не найден, используется admin_email сайта;
- в письмо добавлены данные покупателя, номер заказа, сумма, статус WooCommerce, статус Яндекс.Доставки и состав заказа.

Фильтры для разработчика:
- ama_account_suite_yandex_cancel_blocked_statuses — изменить список статусов Яндекс.Доставки, после которых кнопка отмены скрывается;
- ama_account_suite_customer_cancel_admin_email_recipient — изменить получателей письма администратору.
