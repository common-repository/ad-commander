(()=>{function t(e){return t="function"==typeof Symbol&&"symbol"==typeof Symbol.iterator?function(t){return typeof t}:function(t){return t&&"function"==typeof Symbol&&t.constructor===Symbol&&t!==Symbol.prototype?"symbol":typeof t},t(e)}function e(t,e){return function(t){if(Array.isArray(t))return t}(t)||function(t,e){var a=null==t?null:"undefined"!=typeof Symbol&&t[Symbol.iterator]||t["@@iterator"];if(null!=a){var n,r,c,o,i=[],l=!0,s=!1;try{if(c=(a=a.call(t)).next,0===e){if(Object(a)!==a)return;l=!1}else for(;!(l=(n=c.call(a)).done)&&(i.push(n.value),i.length!==e);l=!0);}catch(t){s=!0,r=t}finally{try{if(!l&&null!=a.return&&(o=a.return(),Object(o)!==o))return}finally{if(s)throw r}}return i}}(t,e)||function(t,e){if(t){if("string"==typeof t)return a(t,e);var n={}.toString.call(t).slice(8,-1);return"Object"===n&&t.constructor&&(n=t.constructor.name),"Map"===n||"Set"===n?Array.from(t):"Arguments"===n||/^(?:Ui|I)nt(?:8|16|32)(?:Clamped)?Array$/.test(n)?a(t,e):void 0}}(t,e)||function(){throw new TypeError("Invalid attempt to destructure non-iterable instance.\nIn order to be iterable, non-array objects must have a [Symbol.iterator]() method.")}()}function a(t,e){(null==e||e>t.length)&&(e=t.length);for(var a=0,n=Array(e);a<e;a++)n[a]=t[a];return n}jQuery(document).ready((function(a){function n(t){for(var a="",n=0,r=Object.entries(t);n<r.length;n++){var c=e(r[n],2),o=c[0],i=c[1],l=o;""!==o&&"woadmin_divider:"===o.substring(0,16)&&(l=""),a+='<option value="'.concat(l,'">').concat(i,"</option>")}return a}function r(t,e,a,n){var r=arguments.length>4&&void 0!==arguments[4]?arguments[4]:[],c='<input type="'.concat(t,'" name="').concat(e,'" id="').concat(a,'" value="').concat(n,'"');return r&&r.length>0&&r.forEach((function(t){c+=" ".concat(t.key,'="').concat(t.value,'"')})),c+=" />"}var c=a("#adcmdrtargetingdiv");c.length>0&&c.find(".adcmdr-targeting").each((function(){var c=a(this),o=c.parent().data("targetingtype")||null;function i(){c.find(".targeting-andor").each((function(){var t=a(this),e=t.val(),n=t.closest("tr");"and"===e?n.removeClass("or-divide"):n.addClass("or-divide")}))}function l(t){var e=adcmdr_targeting.actions.load_ac_results,n=[],c=t.closest("tr").find('select[name*="[target]"]:first');if(c&&!(c.length<=0)){var o=c.val(),i=c.attr("name"),l=c.attr("name"),s=t.find('input[name*="[values]"]'),d=s.siblings(".selected_posts_list");d.length<=0?d=a('<ul class="selected_posts_list adcmdr-remove-controls"></ul>').insertAfter(s):d.children("li").each((function(){var t=a(this);n.push({label:t.find("span").text(),value:parseInt(t.find("button").data("postid"),10)})}));var u=s.siblings('input[name*="[selected_post_ids]"]');u.length<=0&&(u=a(r("hidden",i.replace("[target]","[selected_post_ids]"),l.replace("[target]","[selected_post_ids]"),"")).insertAfter(s)),t.on("click",".adcmdr-remove-post",(function(t){t.preventDefault();var e=a(this),r=parseInt(e.data("postid"),10);n=n.filter((function(t){return t.value!==r})),f()})),s.autocomplete({minLength:0,multiselect:!0,source:function(t,n){if(!t||!t.term)return n();a.getJSON(adcmdr_targeting.ajaxurl,{search_term:t.term,target:o,action:e.action,security:e.security},(function(t){if(!t.success)return n();n(a.map(t.data.results,(function(t,e){return{label:t.title,value:t.id}})))}))},focus:function(){return!1},select:function(t,e){var a;t.preventDefault(),a=e.item.value,n.filter((function(t){return t.value===a})).length>0||(n.push(e.item),f(),s.val(""))}})}function f(){var t;u.val(n.map((function(t){return t.value})).join(",")),t="",n.forEach((function(e){t+='<li>\n\t\t\t\t\t\t\t\t\t<button class="adcmdr-remove-post adcmdr-remove" data-postid="'.concat(e.value,'">\n\t\t\t\t\t\t\t\t\t\t<span>').concat(e.label,'</span>\n\t\t\t\t\t\t\t\t\t\t<i class="dashicons dashicons-minus"></i>\n\t\t\t\t\t\t\t\t\t</button>\n\t\t\t\t\t\t\t\t</li>')})),d.html(t)}}c.on("change",".targeting-andor",(function(){i()})),c.on("resetrows",(function(){i()})),c.find("tbody").on("sortupdate",(function(){i()})),i(),c.on("change",".targeting-target",(function(){var c=a(this),i=c.val(),s=c.closest("td").next("td");if(i){s.html('<span class="adcmdr-loader adcmdr-show"></span>');var d=adcmdr_targeting.actions.load_conditions;a.getJSON(adcmdr_targeting.ajaxurl,{target:i,action:d.action,security:d.security,targeting_type:o},(function(o){if(o.success){var i=c.attr("name"),d=c.attr("id"),u=i.replace("[target]","[condition]"),f=d.replace("[target]","[condition]"),v=i.replace("[target]","[values]"),p=d.replace("[target]","[values]"),g="",m=!1;if(void 0!==o.data.conditions){var h=n(o.data.conditions);g='<select name="'.concat(u,'" id="').concat(f,'" class="targeting-conditions">').concat(h,"</select>")}if(void 0!==o.data.value_type&&void 0!==o.data.values){var b=function(t,c){var i,l,s=arguments.length>2&&void 0!==arguments[2]?arguments[2]:"",d=v,u=p,f=[];if(s&&""!==s&&(d+="["+s+"]",u+="["+s+"]",void 0!==o.data.args&&void 0!==o.data.args[s])){var h=[];h[u]=[];for(var b=0,y=Object.entries(o.data.args[s]);b<y.length;b++){var _=e(y[b],2),j=_[0],k=_[1];h[u].push({key:j,value:k})}f=h[u]}switch(c){case"words":g+='<span class="adcmdr-block-label">'.concat(t,"</span>");break;case"checkgroup":g+=function(t,n,c){var o,i="";if(a.isEmptyObject(t))i='<span class="woforms-notfound adcmdr-block-label">'.concat(adcmdr_targeting.notfound,"</span>");else{n+="[]";for(var l=0,s=Object.entries(t);l<s.length;l++){var d=e(s[l],2),u=d[0],f=d[1],v=c+"_"+u;i+="<span>".concat(r("checkbox",n,v,u)," ").concat((o=f,'<label for="'.concat(v,'">').concat(o,"</label>")),"</span>")}}return'<div class="woforms-input-group">'.concat(i,"</div>")}(t,d,u);break;case"select":g+=(i=t,l=u,'<select name="'.concat(d,'" id="').concat(l,'">').concat(n(i),"</select>"));break;case"text":g+=r("text",d,u,"");break;case"autocomplete":m=!0,adcmdr_targeting.page_ac_placeholder&&f.push({key:"placeholder",value:adcmdr_targeting.page_ac_placeholder}),g+=r("text",d,u,"",f);break;case"number":g+=r("number",d,u,"",f)}},y=o.data.values,_=o.data.value_type;if("object"===t(_))for(var j=0,k=Object.entries(_);j<k.length;j++){var S=e(k[j],2),w=S[0],O=S[1],x="";void 0!==y[w]&&(x=y[w]),"object"===t(o.data.value_type_labels)&&void 0!==o.data.value_type_labels[w]&&b(o.data.value_type_labels[w],"words",w),b(x,O,w)}else b(y,_)}s.html('<div class="adcmdr-targeting-conditions">'.concat(g,"</div>")),m&&l(s)}else s.html("error")}))}else s.html("")})),c.find(".init-ac").each((function(){l(a(this).closest("td"))}))}))}))})();