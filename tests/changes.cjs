/*
 * SPDX-License-Identifier: AGPL-3.0-only
 * Original project author: Kaspars Mednis (madKaspars)
 * AI-generated implementation; see NOTICE.md for provenance.
 */
const assert = require('node:assert/strict');
const {cluster, markerLabel, barMarkers} = require('../assets/js/changes.js');
const entries = Array.from({length:20}, (_,i) => ({id:String(9007199254740993n+BigInt(i)),clock:10+i}));
for(const span of [60,3600,86400,365*86400]) for(const width of [180,400,1000,2000]) for(const spacing of [38,48]) {
    const groups=cluster(entries,[0,span],width,spacing);
    assert.deepEqual(groups.flatMap(g=>g.markers.map(m=>m.id)), entries.map(m=>m.id));
    groups.forEach((g,i)=>{
        assert(g.x>=spacing/2 && g.x<=width-spacing/2);
        if(i)assert(g.x-groups[i-1].x>=spacing);
    });
}
assert.equal(cluster(entries,[100,200],1000).length,0);
assert.equal(cluster(entries,[0,31536000],500).length,1);
assert.equal(cluster(entries,[10,29],2000).length,20);
const three={id:'9007199254740993',clock:10,changeCount:3,changesComplete:true};
assert.equal(markerLabel([three]),'1 update · 3 changes');
assert.equal(cluster([three],[0,60],500).length,1);
assert.equal(markerLabel([three,{...three,id:'9007199254740994',changeCount:2}]),'2 updates · 5 changes');
assert.equal(markerLabel([{changeCount:1,changesComplete:true}]),'1 update · 1 change');
assert.equal(markerLabel([{changeCount:0,changesComplete:false}]),'1 update · changes unavailable');
assert.equal(markerLabel([{changeCount:1,changesComplete:false}]),'1 update · ≥1 change');
assert.equal(markerLabel([three,{changeCount:0,changesComplete:false}]),'2 updates · ≥3 changes');
console.log('PASS: change marker clustering, 1m–365d scales, clipping, touch spacing and large record IDs.');
console.log('PASS: update and operation counts, grouped markers, singular wording and unreadable details.');
const bar={clock:0,expiry:60,recovery:30};
const nearRecovery=[{id:'1',clock:29},{id:'2',clock:30},{id:'3',clock:31}];
for(const width of [200,620,2000])for(const spacing of [38,48]) {
    const placed=barMarkers(nearRecovery,bar,60,[0,60],width,spacing);
    assert.equal(placed.flatMap(group=>group.markers).length,3);
    placed.forEach((group,index)=>{
        assert(group.x>=16 && group.x<=width-16,'Symbol extends outside bar');
        assert(Math.abs(group.x-width/2)>=26,'Symbol covers recovery diamond');
        if(index)assert(group.x-placed[index-1].x>=spacing,'Marker hit targets overlap');
    });
}
assert.equal(barMarkers(nearRecovery,{clock:null,expiry:60},60,[0,60],500).length,0);
assert.equal(barMarkers(nearRecovery,{clock:0,expiry:null},60,[0,60],500).length,0);
assert.equal(barMarkers(nearRecovery,{clock:20,expiry:20},60,[0,60],500).length,0);
assert.equal(barMarkers(nearRecovery,bar,60,[0,60000],500).length,0,'Too-short bar must not move an update elsewhere');
assert.equal(barMarkers(nearRecovery,bar,60,[70,100],500).length,0,'Off-screen markers');
const clipped=barMarkers([{id:'before',clock:9},{id:'inside',clock:20},{id:'after',clock:31},{id:'future',clock:29}],
    {clock:10,expiry:30,recovery:null},25,[0,60],600);
assert.deepEqual(clipped.flatMap(group=>group.markers.map(marker=>marker.id)),['inside']);
assert.equal(barMarkers([{id:'epoch',clock:0}],{clock:0,expiry:60,recovery:null},30,[0,60],600).length,1);
console.log('PASS: symbols inside bars, recovery collision avoidance, grouped spacing, clipping, missing timing and epoch zero.');
