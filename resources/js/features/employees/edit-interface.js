const employeeEditForm = document.querySelector('[data-employee-edit-form]');

if (employeeEditForm) {
    const salaryEffectiveModes = Array.from(employeeEditForm.querySelectorAll('input[name="salary_effective_mode"]'));
    const salaryEffectiveDate = employeeEditForm.querySelector('[data-salary-effective-date]');
    const requiredMessage = 'يجب تحديد تاريخ سريان الراتب عند اختيار تاريخ مخصص.';
    const arabicValidationMessages = {
        name: 'يجب إدخال اسم الموظف.',
        salary: 'يجب إدخال راتب شهري صحيح لا يقل عن صفر.',
        accountant_email: 'يجب إدخال بريد إلكتروني صحيح للمحاسب.',
        accountant_password: 'كلمة المرور الجديدة يجب ألا تقل عن 8 أحرف.',
        accountant_password_confirmation: 'يجب تأكيد كلمة المرور الجديدة بصورة مطابقة.',
        store_id: 'يجب اختيار متجر الموظف.',
        transfer_effective_date: 'تاريخ نقل الموظف غير صحيح.',
    };

    const syncSalaryEffectiveDate = () => {
        const customMode = salaryEffectiveModes.find((input) => input.checked)?.value === 'custom';
        salaryEffectiveDate?.toggleAttribute('required', customMode);
        salaryEffectiveDate?.setAttribute('aria-required', customMode ? 'true' : 'false');
        if (!customMode) salaryEffectiveDate?.setCustomValidity('');
    };

    salaryEffectiveModes.forEach((input) => input.addEventListener('change', syncSalaryEffectiveDate));
    salaryEffectiveDate?.addEventListener('input', () => salaryEffectiveDate.setCustomValidity(''));
    salaryEffectiveDate?.addEventListener('invalid', () => {
        const customMode = salaryEffectiveModes.find((input) => input.checked)?.value === 'custom';
        salaryEffectiveDate.setCustomValidity(customMode && !salaryEffectiveDate.value ? requiredMessage : '');
    });
    employeeEditForm.querySelectorAll('input, select').forEach((field) => {
        if (field === salaryEffectiveDate) return;
        field.addEventListener('invalid', () => {
            field.setCustomValidity(arabicValidationMessages[field.name] || 'راجع قيمة هذا الحقل ثم أعد المحاولة.');
        });
        ['input', 'change'].forEach((eventName) => field.addEventListener(eventName, () => field.setCustomValidity('')));
    });
    syncSalaryEffectiveDate();
}
