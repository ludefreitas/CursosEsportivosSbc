(function(root){
    'use strict';
    function valid(value){
        value=String(value||'');if(/[^0-9.\s-]/u.test(value))return false;
        const n=value.replace(/\D/g,'');if(!/^[12789][0-9]{14}$/.test(n))return false;
        if(/([0-9])\1{10}/.test(n))return false;
        for(let period=1;period<=7;period++)if(n.slice(0,period).repeat(15).slice(0,15)===n)return false;
        for(const direction of [1,-1]){let sequence=true;for(let i=1;i<15;i++)if((Number(n[i-1])+direction+10)%10!==Number(n[i])){sequence=false;break;}if(sequence)return false;}
        for(let start=0;start<=20;start++){let sequence='';for(let i=start;i<start+30;i++)sequence+=String(i);if(sequence.slice(0,15)===n)return false;}
        let sum=0;
        if(n[0]==='1'||n[0]==='2'){
            for(let i=0;i<11;i++)sum+=Number(n[i])*(15-i);
            let digit=11-sum%11;if(digit===11)digit=0;
            const tail=digit===10?'001'+(11-(sum+2)%11):'000'+digit;
            return n===n.slice(0,11)+tail;
        }
        for(let i=0;i<15;i++)sum+=Number(n[i])*(15-i);
        return sum%11===0;
    }
    root.SusCardValidator={isValid:valid};
    if(typeof module!=='undefined')module.exports=root.SusCardValidator;
})(typeof window!=='undefined'?window:globalThis);
