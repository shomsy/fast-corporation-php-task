import test from 'node:test';
import assert from 'node:assert/strict';
import { drawChart, chartSegments, chartTicks } from '../../public/app/ActivityTracking/drawChart.js';
import { showActivity } from '../../public/app/ActivityTracking/showActivity.js';

function node(tag) {
  return {
    tag, children: [], textContent: '', attributes: {}, value: '', href: '',
    append(...children) { this.children.push(...children); },
    replaceChildren(...children) { this.children = children; },
    setAttribute(name, value) { this.attributes[name] = String(value); },
    addEventListener() {}, removeEventListener() {}, focus() {},
  };
}
const page = { createElement: node, createElementNS(_ns, tag) { return node(tag); } };

test('UTC chart respects gaps and derives series directly from the table dataset', () => {
  const days = [
    { date: '2026-10-01', pageAViews: 2, pageBViews: 0, buyCowClicks: 1, downloadClicks: 0 },
    { date: '2026-10-03', pageAViews: 1, pageBViews: 2, buyCowClicks: 0, downloadClicks: 2 },
  ];
  const segments = chartSegments(days, 'pageAViews');
  assert.deepEqual(segments.map((part) => part.length), [1, 1]);
  assert.equal(drawChart(page, days).tag, 'svg');
  assert.deepEqual(days.map((day) => day.pageAViews), [2, 1]);
});

test('statistics uses URL filters without HTML injection and server error denial', async () => {
  const outlet = node('main');
  const paths = [];
  showActivity({ path: '/app/statistics' }, {
    page, outlet, signal: new AbortController().signal, onDispose() {},
  }, {
    client: { request: async (path) => { paths.push(path); return {
      events: [{ occurred_at: '2026-10-01T12:00:00+00:00', actor_label: '<img src=x>', action: 'login', target: null }],
      nextCursor: null, previousCursor: null,
    }; } },
    query: '?user=%3Cimg+src%3Dx%3E&date=2026-10-01',
    navigate() {},
  });
  await new Promise(setImmediate);
  assert.ok(paths[0].includes('user=%3Cimg+src%3Dx%3E'));
  const serialized = JSON.stringify(outlet);
  assert.ok(serialized.includes('<img src=x>'));
  assert.ok(!serialized.includes('innerHTML'));

  const deniedOutlet = node('main');
  showActivity({ path: '/app/reports' }, {
    page, outlet: deniedOutlet, signal: new AbortController().signal, onDispose() {},
  }, {
    client: { request: async () => { throw { status: 403, message: 'Forbidden.' }; } },
    query: '', navigate() {},
  });
  await new Promise(setImmediate);
  assert.ok(JSON.stringify(deniedOutlet).includes('Forbidden.'));
});


test('low-count Y-axis ticks are distinct integers and include zero and one', () => {
  const days = [{ date: '2026-10-01', pageAViews: 1, pageBViews: 0, buyCowClicks: 0, downloadClicks: 0 }];
  assert.deepEqual(chartTicks(days).map((tick) => tick.value), [0, 1]);
  const svg = drawChart(page, days);
  const labels = svg.children.filter((element) => element.tag === 'text').map((element) => element.textContent);
  assert.ok(labels.includes('0'));
  assert.ok(labels.includes('1'));
  assert.ok(labels.includes('2026-10-01'));
});

test('all four chart series have visible legend labels matching their line styles', () => {
  const svg = drawChart(page, [
    { date: '2026-10-01', pageAViews: 2, pageBViews: 3, buyCowClicks: 1, downloadClicks: 4 },
    { date: '2026-10-02', pageAViews: 3, pageBViews: 1, buyCowClicks: 2, downloadClicks: 5 },
  ]);
  const labels = svg.children.filter((item) => item.tag === 'text').map((item) => item.textContent);
  const names = ['Page A views', 'Page B views', 'Buy cow clicks', 'Download clicks'];
  names.forEach((label) => assert.ok(labels.includes(label), label));
  const legend = svg.children.filter((item) => item.tag === 'line' && item.attributes.y1 === '20');
  const lines = svg.children.filter((item) => item.tag === 'polyline');
  assert.equal(legend.length, 4);
  assert.equal(lines.length, 4);
  legend.forEach((sample, index) => {
    assert.equal(sample.attributes.stroke, lines[index].attributes.stroke);
    assert.equal(sample.attributes['stroke-dasharray'], lines[index].attributes['stroke-dasharray']);
  });
});
