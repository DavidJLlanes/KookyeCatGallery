const assert = require('node:assert/strict');
const fs = require('node:fs');
const path = require('node:path');
const vm = require('node:vm');
const engine = require('../assets/js/photo-filter-engine.js');
const context = { window: {} };
vm.runInNewContext(fs.readFileSync(path.join(__dirname, '../assets/js/photo-editor-presets.js'), 'utf8'), context);
const { version, presets, families } = context.window.photoEditorPresetData;
assert.equal(version, 2);
assert.equal(presets.length, 100);
assert.equal(families.length, 10);
assert.equal(new Set(presets.map(p => p.id)).size, 100);
assert.equal(new Set(presets.map(p => p.name)).size, 100);
for (const f of families) assert.equal(presets.filter(p => p.category === f.id).length, 10);
for (const p of presets) {
  assert.equal(p.group, families.find(f => f.id === p.category).title);
  assert(p.description.length > 25, p.name + ' needs an effect description.');
}

// A neutral scale, skin, foliage, sky, saturated colours, and bright lamps.
const w = 96, h = 64, original = new Uint8ClampedArray(w * h * 4);
const colors = [[211,148,110],[41,92,36],[33,103,179],[184,29,38],[24,181,175],[207,49,173],[219,198,67],[99,65,41]];
for (let y = 0; y < h; y++) for (let x = 0; x < w; x++) {
  const i = (y * w + x) * 4, light = x / (w - 1);
  const base = y < 16 ? [255,255,255] : colors[Math.floor((y - 16) / 6)];
  for (let c = 0; c < 3; c++) original[i+c] = base[c] * light;
  original[i+3] = 200 + x % 56;
}
assert.deepEqual(engine.apply(original.slice(),w,h,{},1), original, 'An identity recipe is actually neutral.');
const results = presets.map((p, i) => {
  const full = engine.apply(original.slice(),w,h,p.grade,1,i+1);
  assert.deepEqual(engine.apply(original.slice(),w,h,p.grade,0,i+1), original, p.name + ': 0% must remove every filter effect.');
  assert.deepEqual(engine.apply(original.slice(),w,h,p.grade,1,i+1), full, p.name + ': grain and dust must not flicker.');
  const half = engine.apply(original.slice(),w,h,p.grade,.5,i+1);
  for (let n = 0; n < full.length; n++) {
    if (n % 4 === 3) {
      assert.equal(full[n],original[n],p.name+': preserve transparency');
      assert.equal(half[n],original[n]);
    } else assert(Math.abs(half[n] - (original[n]+full[n])*.5) <= 1, p.name+': intensity must blend the complete result.');
  }
  return full;
});
const distance = (a,b) => {
  let total=0;
  for(let n=0;n<a.length;n+=4)for(let c=0;c<3;c++)total+=Math.abs(a[n+c]-b[n+c]);
  return total/(a.length/4*3);
};
const nearest = presets.map(()=>Infinity);
for (let i=0;i<100;i++) for(let j=i+1;j<100;j++) {
  const d=distance(results[i],results[j]);
  assert(d > 3, 'Visually near-duplicate recipes: '+presets[i].name+' / '+presets[j].name+' ('+d.toFixed(2)+')');
  nearest[i]=Math.min(nearest[i],d);nearest[j]=Math.min(nearest[j],d);
}
nearest.sort((a,b)=>a-b);
assert(nearest[50] > 8, 'The catalogue should have broadly separated rendered results.');
const pixel = new Float64Array(3);
for(const p of presets.filter(p=>p.category==='cross')){
  let maxSpread=0;
  for(const gray of [32,96,160,224]){
    engine.gradePixel(gray,gray,gray,engine.prepare(p.grade),pixel);
    maxSpread=Math.max(maxSpread,Math.max(...pixel)-Math.min(...pixel));
  }
  assert(maxSpread > 45,p.name+': crossed channel responses must be visible on neutrals.');
}
for(const p of presets.filter(p=>p.category==='toned')){
  engine.gradePixel(96,96,96,engine.prepare(p.grade),pixel);
  assert(Math.max(...pixel)-Math.min(...pixel)>8,p.name+': a virado needs an actual ink colour.');
}
for(const p of presets.filter(p=>p.category==='vintage')){
  assert(p.grade.grain || p.grade.dust,p.name+': aged film needs texture.');
  assert(p.grade.curve && (p.grade.channels || p.grade.hsl),p.name+': aged film needs a tone curve and selective colour response.');
}
const lamp = new Uint8ClampedArray(64*64*4);
for(let y=0;y<64;y++)for(let x=0;x<64;x++){
  const i=(y*64+x)*4,white=x>=26&&x<=37&&y>=26&&y<=37?255:0;
  lamp.set([white,white,white,255],i);
}
const halo=engine.apply(lamp.slice(),64,64,{halation:{amount:1,radius:.06,threshold:.6}},1);
const adjacent=(32*64+24)*4;
assert(halo[adjacent]>lamp[adjacent]+8,'Halation must spread outside bright pixels.');
assert(halo[adjacent]>halo[adjacent+1] && halo[adjacent+1]>=halo[adjacent+2],'Halation should make a warm halo.');
const bloomed=engine.apply(lamp.slice(),64,64,{bloom:{amount:.8,radius:.06,threshold:.6}},1);
assert(bloomed[adjacent]>lamp[adjacent]+8,'Bloom must spread to neighbouring pixels.');
const cyan=presets.find(p=>p.name==='Cianotipia azul Prusia');
engine.gradePixel(85,85,85,engine.prepare(cyan.grade),pixel);
assert(pixel[2]>pixel[0]+40,'Cyanotype must produce recognisable Prussian blue.');
console.log('100 independently rendered recipes verified: diverse output, crossed RGB curves, virados, film texture, spatial halos, deterministic effects and 0/50/100% intensity. Median nearest RGB distance: '+nearest[50].toFixed(2));
