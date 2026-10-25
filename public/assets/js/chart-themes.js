(function(window){
    'use strict';
    const App=window.App=window.App||{},key='sbc-admin-chart-theme';let theme;
    try{theme=localStorage.getItem(key);}catch(error){}
    if(!['light','dark'].includes(theme))theme=window.matchMedia?.('(prefers-color-scheme: dark)').matches?'dark':'light';
    function apply(root){root.dataset.chartTheme=theme;const button=root.querySelector('[data-chart-theme-toggle]');if(button){button.textContent=theme==='dark'?'Usar tema claro':'Usar tema escuro';button.setAttribute('aria-pressed',String(theme==='dark'));}}
    App.chartThemes={initialize(root){apply(root);const button=root.querySelector('[data-chart-theme-toggle]');if(!button||button.dataset.themeBound)return;button.dataset.themeBound='1';button.addEventListener('click',()=>{theme=theme==='dark'?'light':'dark';try{localStorage.setItem(key,theme);}catch(error){}document.querySelectorAll('.admin-charts').forEach(apply);});}};
    window.addEventListener('storage',e=>{if(e.key===key&&['light','dark'].includes(e.newValue)){theme=e.newValue;document.querySelectorAll('.admin-charts').forEach(apply);}});
}(window));
