/**
 * User-facing messages for AI Site Editor execution error codes.
 * Backend returns error_code for transparency; we map to clear copy.
 * Never pretend success – if a change was not possible, say exactly why.
 */
const ERROR_CODE_MESSAGES_EN: Record<string, string> = {
    page_not_found: 'Page not found. Try specifying the page or use the home page.',
    site_operations_failed: 'Could not update theme or global header/footer. The change was not applied.',
    change_set_failed: 'Could not apply the requested changes to the page structure.',
    validation_failed: 'The change could not be applied to the current builder data. Try a more specific request or reselect the target.',
    patcher_failed: 'Could not save the draft. The change was not applied.',
    component_no_editable_param: 'That component has no editable parameter for this change. The target may be read-only or not support the requested field.',
    media_upload_failed: 'Logo or media upload failed. The media endpoint returned an error. Try a different image or try again later.',
    global_only_executor: 'That change affects the global header or footer, but the executor only updates local page sections. Use site-wide settings to edit global components.',
    target_not_found: 'Could not find the target section or component on this page. It may have been removed or the page structure changed.',
    selected_target_scope_violation: 'The request tried to change content outside the selected element. Select a broader target or ask explicitly for a wider change.',
    selected_target_unmappable: 'The selected element is not mapped to a safe editable builder field yet, so chat could not change it.',
    no_effect: 'The request ran, but it did not produce any visible change.',
    executor_exception: 'An unexpected error occurred while applying changes. Check the console or server logs for details.',
};

const ERROR_CODE_MESSAGES_KA: Record<string, string> = {
    page_not_found: 'გვერდი ვერ მოიძებნა. მიუთითე გვერდი ან გამოიყენე მთავარი გვერდი.',
    site_operations_failed: 'თემის ან გლობალური header/footer-ის განახლება ვერ მოხდა.',
    change_set_failed: 'მოთხოვნილი ცვლილებები გვერდის სტრუქტურაზე ვერ გამოვიყენეთ.',
    validation_failed: 'ცვლილება მიმდინარე builder მონაცემებზე ვერ გამოვიყენეთ. სცადე უფრო ზუსტი მოთხოვნა ან თავიდან აირჩიე target.',
    patcher_failed: 'შავი ნახატის შენახვა ვერ მოხდა. ცვლილება არ გამოვიყენეთ.',
    component_no_editable_param: 'ამ კომპონენტს არ აქვს რედაქტირებადი პარამეტრი ამ ცვლილებისთვის.',
    media_upload_failed: 'ლოგოს ან მედიის ატვირთვა ვერ მოხდა. სცადე სხვა სურათი ან მოგვიანებით.',
    global_only_executor: 'ეს ცვლილება ეხება გლობალურ header/footer-ს. გლობალური კომპონენტების რედაქტირება საიტის პარამეტრებიდან.',
    target_not_found: 'სამიზნე სექცია ან კომპონენტი ამ გვერდზე ვერ მოიძებნა.',
    selected_target_scope_violation: 'მოთხოვნა გასცდა არჩეული ელემენტის ფარგლებს. აირჩიე უფრო ფართო target ან პირდაპირ მიუთითე, რომ უფრო ფართო ცვლილება გინდა.',
    selected_target_unmappable: 'არჩეული ელემენტი ჯერ უსაფრთხო builder field-ზე არ არის მიბმული, ამიტომ ჩატმა მისი ზუსტად შეცვლა ვერ შეძლო.',
    no_effect: 'ოპერაცია გაეშვა, მაგრამ ხილული ცვლილება არ დაფიქსირდა.',
    executor_exception: 'ცვლილებების გამოყენებისას მოხდა შეცდომა. დეტალებისთვის შეამოწმე კონსოლი ან სერვერის ლოგები.',
};

/**
 * Returns a user-facing error message for the AI agent execute failure.
 * Uses error_code if we have a known mapping, otherwise falls back to the server error message.
 * Reply language follows the user message: pass locale 'ka' for Georgian, 'en' for English.
 */
export function getAgentErrorMessage(error?: string | null, errorCode?: string | null, locale?: 'ka' | 'en'): string {
    const messages = locale === 'ka' ? ERROR_CODE_MESSAGES_KA : ERROR_CODE_MESSAGES_EN;
    const fallback = locale === 'ka' ? 'ცვლილებების გამოყენება ვერ მოხდა.' : 'Failed to apply changes.';
    if (errorCode && messages[errorCode]) {
        return messages[errorCode];
    }
    return error?.trim() || fallback;
}
