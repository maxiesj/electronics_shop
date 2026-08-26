"use strict";loaded_g_0(function(_){var window=this;
_.h("pRMv2d");
_.sy(_.bz,class extends _.vy{static Wa(){return{}}constructor(){super();this.places=new Map;this.ja=null;this.Aa=new Set;this.ya=new Set;this.xa=new Set;this.va=new Set;this.fa=!1;this.La=_.Tl(this.Ma,100,this)}oa(a){return this.places.get(a)}aa(){return[...this.places.values()]}Ca(){this.fa=!1;this.places.clear()}ba(...a){var b=!1;for(let c of a){let d=this.places.get(c.mid);d?(!d.latLng&&c.latLng&&(d.latLng=c.latLng,b=!0),!d.fprint&&c.fprint&&(d.fprint=c.fprint,b=!0),!d.categoryName&&c.categoryName&&
(d.categoryName=c.categoryName,b=!0),a=Object.fromEntries(Object.entries(c).filter(([e,f])=>!d[e]&&(f!==void 0||f!==""))),Object.assign(d,a)):this.places.set(c.mid,Object.assign({},c));b=!0}b&&this.La()}da(a){this.ja=a?Object.assign({},a):null;for(let b of this.ya)b(this.ja)}Fa(a){this.ya.add(a);this.ja&&a(this.ja)}Ja(a){this.Aa.add(a);var b=this.aa();b.length&&a(b)}Ha(a){this.xa.add(a);this.fa&&this.aa().filter(b=>!!b.latLng).length===0&&a()}Sa(a){this.va.add(a);this.fa&&a()}na(){this.fa=!0;this.aa().filter(a=>
!!a.latLng).length===0&&this.xa.forEach(a=>void a());this.va.forEach(a=>void a())}Ma(){var a=this.aa();if(a.length)for(let b of this.Aa)b(a)}});
_.q();
});
// Google Inc.
