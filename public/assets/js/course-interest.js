(function (window, $) {
    'use strict';
    const recent = new Map();
    const locationSelectors = ['.home-location-suggestion[data-location-id]', '[data-home-location-select]', '[data-home-modality-location]', '[data-home-cpf-location]'];
    const modalitySelectors = ['[data-home-course-modality-select]', '[data-home-location-modality]'];
    const classSelectors = ['[data-home-course-enroll]', '[data-home-course-vacancies]', '[data-home-cpf-class]'];
    document.addEventListener('click', function (event) {
        const target = event.target.closest(locationSelectors.concat(modalitySelectors, classSelectors).join(','));
        if (!target || target.disabled) return;
        let type, value;
        if (target.matches(classSelectors.join(','))) { type='turma'; value=target.getAttribute('data-home-course-enroll') || target.getAttribute('data-home-course-vacancies') || target.getAttribute('data-home-cpf-class'); }
        else if (target.matches(modalitySelectors.join(','))) { type='modalidade'; value=target.getAttribute('data-home-course-modality-select') || target.getAttribute('data-home-location-modality'); }
        else { type='local'; value=target.getAttribute('data-location-id') || target.getAttribute('data-home-location-select') || target.getAttribute('data-home-modality-location') || target.getAttribute('data-home-cpf-location'); }
        const id=Number(value), key=type+':'+id, now=Date.now();
        if (!Number.isSafeInteger(id) || id<1 || now-(recent.get(key)||0)<2000) return;
        recent.set(key,now);
        if(recent.size>100)for(const[k,time]of recent)if(now-time>60000)recent.delete(k);
        if (!window.App || !window.App.core) return;
        $.ajax({url:window.App.core.buildUrl('/cursos/interesse'),method:'POST',data:{tipo:type,id:id},suppressGlobalLoading:true,headers:{'X-Requested-With':'XMLHttpRequest',Accept:'application/json'}});
    }, true);
}(window, window.jQuery));
