const metrics = [
  { key: 'pageAViews', label: 'Page A views', color: '#1d4ed8', dash: '' },
  { key: 'pageBViews', label: 'Page B views', color: '#166534', dash: '8 4' },
  { key: 'buyCowClicks', label: 'Buy cow clicks', color: '#b91c1c', dash: '3 4' },
  { key: 'downloadClicks', label: 'Download clicks', color: '#6d28d9', dash: '9 3 2 3' },
];

function svgNode(page, name, attributes = {}, text = null) {
  const node = page.createElementNS('http://www.w3.org/2000/svg', name);
  for (const [key, value] of Object.entries(attributes)) node.setAttribute(key, String(value));
  if (text !== null) node.textContent = String(text);
  return node;
}

/** Calendar-space chart points: missing UTC dates separate, not interpolate, active days. */
export function chartSegments(days, metric) {
  if (days.length === 0) return [];
  const epochs = days.map((day) => Date.parse(day.date + 'T00:00:00Z'));
  const max = Math.max(1, ...days.flatMap((day) => metrics.map((series) => Number(day[series.key]) || 0)));
  const span = Math.max(86400000, epochs[epochs.length - 1] - epochs[0]);
  const pieces = [];
  let current = [];
  days.forEach((day, index) => {
    if (index > 0 && epochs[index] - epochs[index - 1] > 86400000) {
      if (current.length) pieces.push(current);
      current = [];
    }
    const count = Number(day[metric]) || 0;
    current.push({ x: 65 + (epochs[index] - epochs[0]) * 750 / span,
      y: 230 - count * 185 / max, date: day.date, count });
  });
  if (current.length) pieces.push(current);
  return pieces;
}

/** Unique, integer-valued axis ticks, including separate 0/1 for a single-count series. */
export function chartTicks(days) {
  const max = Math.max(1, ...days.flatMap((day) => metrics.map((series) => Number(day[series.key]) || 0)));
  const steps = Math.min(4, max);
  const values = [...new Set(Array.from({ length: steps + 1 }, (_, index) => Math.round(max * index / steps)))];
  return values.map((value) => ({ value, y: 230 - value * 185 / max }));
}

/** Render the same authoritative daily dataset that also renders the HTML table. */
export function drawChart(page, days) {
  const svg = svgNode(page, 'svg', {
    viewBox: '0 0 860 290', role: 'img',
    'aria-labelledby': 'daily-chart-title daily-chart-desc',
  });
  svg.append(svgNode(page, 'title', { id: 'daily-chart-title' }, 'Daily activity totals (UTC)'),
    svgNode(page, 'desc', { id: 'daily-chart-desc' },
      'One line per tracked action. Missing UTC days break the lines. Exact totals follow in the table.'));
  svg.append(svgNode(page, 'line', { x1: 65, y1: 45, x2: 65, y2: 230, stroke: '#64748b' }),
    svgNode(page, 'line', { x1: 65, y1: 230, x2: 815, y2: 230, stroke: '#64748b' }));
  for (const tick of chartTicks(days)) {
    svg.append(svgNode(page, 'line', {
      x1: 65, y1: tick.y, x2: 815, y2: tick.y, stroke: '#d9e1ee',
    }));
    svg.append(svgNode(page, 'text', {
      x: 53, y: tick.y + 4, 'text-anchor': 'end', 'font-size': 12, fill: '#465971',
    }, tick.value));
  }
  if (days.length) {
    const edges = days.length === 1 ? [0] : [0, days.length - 1];
    for (const index of edges) {
      const date = days[index].date;
      const x = chartSegments(days, metrics[0].key).flat()[index]?.x
        ?? (index === 0 ? 65 : 815);
      svg.append(svgNode(page, 'text', {
        x: x.toFixed(2), y: 259, 'text-anchor': index === 0 ? 'start' : 'end',
        'font-size': 12, fill: '#465971',
      }, date));
    }
  }
  for (const series of metrics) {
    for (const piece of chartSegments(days, series.key)) {
      if (piece.length > 1) svg.append(svgNode(page, 'polyline', {
        points: piece.map((point) => point.x.toFixed(2) + ',' + point.y.toFixed(2)).join(' '),
        stroke: series.color, 'stroke-width': 3, 'stroke-dasharray': series.dash, fill: 'none',
      }));
      for (const point of piece) {
        const circle = svgNode(page, 'circle', {
          cx: point.x.toFixed(2), cy: point.y.toFixed(2), r: 5,
          fill: series.color, tabindex: 0,
          'aria-label': series.label + ': ' + point.count + ' on ' + point.date,
        });
        circle.append(svgNode(page, 'title', {}, series.label + ': ' + point.count + ' on ' + point.date));
        svg.append(circle);
      }
    }
  }
  return svg;
}
