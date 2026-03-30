import json
import os
import re
import tempfile
from pathlib import Path

import pandas as pd

try:
    os.environ.setdefault("MPLCONFIGDIR", tempfile.mkdtemp(prefix="matplotlib-"))
    import matplotlib
    matplotlib.use("Agg")
    import matplotlib.pyplot as plt
except ImportError:
    plt = None


DATA_PATH = Path("alarms.json")
OUTPUT_CSV = Path("haifa_alarms_subset.csv")
OUTPUT_AREA_CSV = Path("haifa_alarms_by_area.csv")
OUTPUT_CHART = Path("haifa_alarm_hours.png")
OUTPUT_HEATMAP = Path("haifa_alarm_hour_by_date.png")
OUTPUT_TIMELINE = Path("haifa_alarm_timeline.png")
OUTPUT_AREA_HOURLY = Path("haifa_alarm_hours_by_area.png")
OUTPUT_AREA_TIMELINE = Path("haifa_alarm_timeline_by_area.png")
OUTPUT_DASHBOARD = Path("haifa_alarms.html")

# Match any city that contains one of these substrings.
LOCATION_KEYWORDS = ["Haifa", "חיפה"]


def load_alarm_data(path: Path):
    if not path.exists():
        raise FileNotFoundError(f"{path} was not found")

    if path.stat().st_size == 0:
        raise ValueError(f"{path} is empty")

    raw_text = path.read_text(encoding="utf-8")

    try:
        return json.loads(raw_text)
    except json.JSONDecodeError:
        fixed_text = raw_text

        # Some files are missing the opening "[" for the first record.
        if re.match(r"^\[\s*\d", raw_text):
            fixed_text = "[" + raw_text

        return json.loads(fixed_text)


def city_matches(city: str, keywords: list[str]) -> bool:
    city_lower = city.lower()
    return any(keyword.lower() in city_lower for keyword in keywords)


def write_dashboard(area_hour_counts: pd.DataFrame, output_path: Path) -> None:
    hourly_data = {
        "All areas": area_hour_counts.sum(axis=1).astype(int).tolist()
    }
    for area in area_hour_counts.columns:
        hourly_data[area] = area_hour_counts[area].astype(int).tolist()

    dashboard_payload = {
        "hours": list(range(24)),
        "series": hourly_data,
    }

    html = f"""<!doctype html>
<html lang="en">
<head>
  <meta charset="utf-8">
  <meta name="viewport" content="width=device-width, initial-scale=1">
  <title>Haifa Alarm Hours</title>
  <style>
    :root {{
      --bg: #f3f6fb;
      --surface: #ffffff;
      --text: #162033;
      --muted: #5b6880;
      --primary: #2563eb;
      --border: #d9e1f0;
      --grid: #e9eef7;
    }}
    * {{ box-sizing: border-box; }}
    body {{
      margin: 0;
      font-family: system-ui, -apple-system, BlinkMacSystemFont, "Segoe UI", sans-serif;
      background: linear-gradient(180deg, #eef4ff 0%, var(--bg) 100%);
      color: var(--text);
    }}
    .wrap {{
      max-width: 1100px;
      margin: 0 auto;
      padding: 32px 20px 48px;
    }}
    .card {{
      background: var(--surface);
      border: 1px solid var(--border);
      border-radius: 20px;
      box-shadow: 0 20px 60px rgba(37, 99, 235, 0.10);
      padding: 24px;
    }}
    h1 {{
      margin: 0 0 8px;
      font-size: 40px;
      line-height: 1.05;
    }}
    p {{
      margin: 0;
      color: var(--muted);
      font-size: 16px;
    }}
    .controls {{
      display: flex;
      flex-wrap: wrap;
      align-items: end;
      gap: 16px;
      margin: 28px 0 24px;
    }}
    .control {{
      min-width: 260px;
    }}
    label {{
      display: block;
      margin-bottom: 8px;
      font-size: 13px;
      font-weight: 700;
      letter-spacing: 0.04em;
      text-transform: uppercase;
      color: var(--muted);
    }}
    select {{
      width: 100%;
      padding: 12px 14px;
      border: 1px solid var(--border);
      border-radius: 12px;
      background: #fff;
      font-size: 16px;
      color: var(--text);
    }}
    .stats {{
      display: flex;
      gap: 12px;
      flex-wrap: wrap;
      margin-bottom: 18px;
    }}
    .pill {{
      padding: 10px 14px;
      border-radius: 999px;
      background: #eef4ff;
      color: #234aa8;
      font-weight: 600;
      font-size: 14px;
    }}
    svg {{
      width: 100%;
      height: auto;
      display: block;
    }}
    .footer {{
      margin-top: 18px;
      color: var(--muted);
      font-size: 14px;
    }}
  </style>
</head>
<body>
  <div class="wrap">
    <div class="card">
      <h1>Haifa Alarm Dashboard</h1>
      <p>Choose an area to inspect the hourly alarm pattern. The graph starts with all Haifa areas combined.</p>

      <div class="controls">
        <div class="control">
          <label for="areaSelect">Area</label>
          <select id="areaSelect"></select>
        </div>
      </div>

      <div class="stats">
        <div class="pill" id="totalPill">Total alarms: 0</div>
        <div class="pill" id="peakPill">Peak hour: -</div>
      </div>

      <svg id="chart" viewBox="0 0 980 420" aria-label="Alarm count by hour"></svg>
      <div class="footer" id="summary"></div>
    </div>
  </div>

  <script>
    const payload = {json.dumps(dashboard_payload, ensure_ascii=False)};
    const select = document.getElementById("areaSelect");
    const chart = document.getElementById("chart");
    const totalPill = document.getElementById("totalPill");
    const peakPill = document.getElementById("peakPill");
    const summary = document.getElementById("summary");

    const areas = Object.keys(payload.series);
    areas.forEach((area) => {{
      const option = document.createElement("option");
      option.value = area;
      option.textContent = area;
      select.appendChild(option);
    }});

    function render(area) {{
      const values = payload.series[area];
      const width = 980;
      const height = 420;
      const margin = {{ top: 30, right: 20, bottom: 60, left: 56 }};
      const plotWidth = width - margin.left - margin.right;
      const plotHeight = height - margin.top - margin.bottom;
      const maxValue = Math.max(...values, 1);
      const barWidth = plotWidth / values.length * 0.72;
      const gap = plotWidth / values.length * 0.28;

      const total = values.reduce((sum, value) => sum + value, 0);
      const peakValue = Math.max(...values);
      const peakHour = values.indexOf(peakValue);

      totalPill.textContent = `Total alarms: ${{total}}`;
      peakPill.textContent = `Peak hour: ${{String(peakHour).padStart(2, "0")}}:00 (${{peakValue}})`;
      summary.textContent = `Showing hourly alarm counts for ${{area}} across 24 hours.`;

      const gridLines = Array.from({{ length: 6 }}, (_, i) => {{
        const value = Math.round(maxValue * i / 5);
        const y = margin.top + plotHeight - (plotHeight * i / 5);
        return `
          <line x1="${{margin.left}}" y1="${{y}}" x2="${{width - margin.right}}" y2="${{y}}" stroke="#e9eef7" stroke-width="1" />
          <text x="${{margin.left - 10}}" y="${{y + 4}}" text-anchor="end" font-size="12" fill="#6b7280">${{value}}</text>
        `;
      }}).join("");

      const bars = values.map((value, index) => {{
        const x = margin.left + index * (barWidth + gap) + gap / 2;
        const barHeight = (value / maxValue) * plotHeight;
        const y = margin.top + plotHeight - barHeight;
        const fill = index === peakHour ? "#dc2626" : "#2563eb";
        return `
          <rect x="${{x.toFixed(2)}}" y="${{y.toFixed(2)}}" width="${{barWidth.toFixed(2)}}" height="${{barHeight.toFixed(2)}}" rx="6" fill="${{fill}}" />
          <text x="${{(x + barWidth / 2).toFixed(2)}}" y="${{margin.top + plotHeight + 20}}" text-anchor="middle" font-size="11" fill="#5b6880">${{index}}</text>
          <text x="${{(x + barWidth / 2).toFixed(2)}}" y="${{Math.max(y - 6, margin.top + 12).toFixed(2)}}" text-anchor="middle" font-size="11" fill="#23324d">${{value}}</text>
        `;
      }}).join("");

      chart.innerHTML = `
        <rect x="0" y="0" width="${{width}}" height="${{height}}" fill="white" rx="18" />
        ${{gridLines}}
        <line x1="${{margin.left}}" y1="${{margin.top + plotHeight}}" x2="${{width - margin.right}}" y2="${{margin.top + plotHeight}}" stroke="#9aa8bf" stroke-width="1.2" />
        <line x1="${{margin.left}}" y1="${{margin.top}}" x2="${{margin.left}}" y2="${{margin.top + plotHeight}}" stroke="#9aa8bf" stroke-width="1.2" />
        ${{bars}}
        <text x="${{width / 2}}" y="${{height - 14}}" text-anchor="middle" font-size="13" fill="#5b6880">Hour of day</text>
        <text x="18" y="${{height / 2}}" text-anchor="middle" font-size="13" fill="#5b6880" transform="rotate(-90 18 ${{height / 2}})">Alarm count</text>
      `;
    }}

    select.addEventListener("change", (event) => render(event.target.value));
    select.value = "All areas";
    render(select.value);
  </script>
</body>
</html>
"""

    output_path.write_text(html, encoding="utf-8")


data = load_alarm_data(DATA_PATH)
rows = []

for item in data:
    event_id = item[0]
    alert_type = item[1]
    cities = item[2]
    ts = item[3]

    matched_cities = [city for city in cities if city_matches(city, LOCATION_KEYWORDS)]
    if not matched_cities:
        continue

    dt = pd.to_datetime(ts, unit="s", utc=True).tz_convert("Asia/Jerusalem")
    rows.append({
        "event_id": event_id,
        "alert_type": alert_type,
        "cities": ", ".join(cities),
        "matched_cities": ", ".join(matched_cities),
        "timestamp": ts,
        "datetime": dt,
        "date": dt.date(),
        "weekday": dt.day_name(),
        "hour": dt.hour,
        "minute_of_day": dt.hour * 60 + dt.minute + dt.second / 60.0,
    })

df = pd.DataFrame(rows).sort_values("datetime").reset_index(drop=True)

if df.empty:
    print("No alarms matched the location keywords:", LOCATION_KEYWORDS)
    raise SystemExit(0)

df.to_csv(OUTPUT_CSV, index=False)

area_rows = []
for row in df.to_dict(orient="records"):
    matched_areas = [city.strip() for city in row["matched_cities"].split(",") if city.strip()]
    for area in matched_areas:
        expanded = dict(row)
        expanded["area"] = area
        area_rows.append(expanded)

area_df = pd.DataFrame(area_rows).sort_values(["datetime", "area"]).reset_index(drop=True)
area_df.to_csv(OUTPUT_AREA_CSV, index=False)

hour_counts = df.groupby("hour").size().reindex(range(24), fill_value=0)

print("Matched rows:", len(df))
print("Matched location keywords:", LOCATION_KEYWORDS)
print("Saved filtered dataset to:", OUTPUT_CSV)
print("Saved exploded area dataset to:", OUTPUT_AREA_CSV)
print()
print(df[["datetime", "hour", "matched_cities"]].head())
print()
print("Alarm count by hour:")
print(hour_counts.to_string())
print()
print("Alarm count by area:")
print(area_df["area"].value_counts().to_string())

if plt is None:
    print()
    print("matplotlib is not installed, so charts were not generated.")
else:
    plt.figure(figsize=(10, 5))
    hour_counts.plot(kind="bar", color="#2563eb")
    plt.title("Haifa Area Alarms by Hour")
    plt.xlabel("Hour of Day")
    plt.ylabel("Alarm Count")
    plt.tight_layout()
    plt.savefig(OUTPUT_CHART, dpi=160)
    plt.close()

    heatmap = (
        df.groupby(["date", "hour"])
        .size()
        .unstack(fill_value=0)
        .reindex(columns=range(24), fill_value=0)
    )

    plt.figure(figsize=(12, max(4, len(heatmap) * 0.25)))
    plt.imshow(heatmap.values, aspect="auto", cmap="Blues")
    plt.colorbar(label="Alarm Count")
    plt.title("Haifa Area Alarms by Date and Hour")
    plt.xlabel("Hour of Day")
    plt.ylabel("Date")
    plt.xticks(range(24), range(24))
    plt.yticks(range(len(heatmap.index)), [str(d) for d in heatmap.index], fontsize=8)
    plt.tight_layout()
    plt.savefig(OUTPUT_HEATMAP, dpi=160)
    plt.close()

    area_names = sorted(area_df["area"].dropna().unique())
    area_cmap = plt.get_cmap("tab10")
    area_color_map = {
        area: area_cmap(i % 10)
        for i, area in enumerate(area_names)
    }

    area_hour_counts = (
        area_df.groupby(["hour", "area"])
        .size()
        .unstack(fill_value=0)
        .reindex(range(24), fill_value=0)
    )

    plt.figure(figsize=(12, 6))
    bottom = None
    for area in area_names:
        values = area_hour_counts[area]
        plt.bar(
            area_hour_counts.index,
            values,
            bottom=bottom,
            color=area_color_map[area],
            label=area,
            width=0.85,
        )
        bottom = values if bottom is None else bottom + values
    plt.title("Haifa Area Alarms by Hour and Area")
    plt.xlabel("Hour of Day")
    plt.ylabel("Alarm Count")
    plt.xticks(range(24))
    plt.legend(title="Area", fontsize=8)
    plt.tight_layout()
    plt.savefig(OUTPUT_AREA_HOURLY, dpi=160)
    plt.close()

    timeline_df = df.copy()
    unique_dates = sorted(timeline_df["date"].astype(str).unique())
    date_to_y = {date: idx for idx, date in enumerate(unique_dates)}
    timeline_df["date_str"] = timeline_df["date"].astype(str)
    timeline_df["y"] = timeline_df["date_str"].map(date_to_y)

    unique_alert_types = sorted(timeline_df["alert_type"].dropna().unique())
    cmap = plt.get_cmap("tab10")
    color_map = {
        alert_type: cmap(i % 10)
        for i, alert_type in enumerate(unique_alert_types)
    }
    timeline_colors = timeline_df["alert_type"].map(color_map)

    plt.figure(figsize=(14, max(6, len(unique_dates) * 0.24)))
    plt.scatter(
        timeline_df["minute_of_day"] / 60.0,
        timeline_df["y"],
        c=timeline_colors,
        s=28,
        alpha=0.8,
        edgecolors="none",
    )
    plt.title("Haifa Area Alarm Timeline by Day")
    plt.xlabel("Hour of Day")
    plt.ylabel("Date")
    plt.xlim(0, 24)
    plt.xticks(range(0, 25, 2))
    plt.yticks(range(len(unique_dates)), unique_dates, fontsize=8)
    plt.grid(axis="x", linestyle="--", alpha=0.3)
    plt.gca().invert_yaxis()

    if unique_alert_types:
        handles = [
            plt.Line2D(
                [0], [0],
                marker="o",
                color="w",
                label=f"alert_type={alert_type}",
                markerfacecolor=color_map[alert_type],
                markersize=7,
            )
            for alert_type in unique_alert_types
        ]
        plt.legend(handles=handles, title="Alarm Type", loc="upper right")

    plt.tight_layout()
    plt.savefig(OUTPUT_TIMELINE, dpi=160)
    plt.close()

    area_timeline_df = area_df.copy()
    area_timeline_df["date_str"] = area_timeline_df["date"].astype(str)
    area_timeline_df["y"] = area_timeline_df["date_str"].map(date_to_y)
    area_timeline_colors = area_timeline_df["area"].map(area_color_map)

    plt.figure(figsize=(14, max(6, len(unique_dates) * 0.24)))
    plt.scatter(
        area_timeline_df["minute_of_day"] / 60.0,
        area_timeline_df["y"],
        c=area_timeline_colors,
        s=30,
        alpha=0.8,
        edgecolors="none",
    )
    plt.title("Haifa Alarm Timeline by Day and Area")
    plt.xlabel("Hour of Day")
    plt.ylabel("Date")
    plt.xlim(0, 24)
    plt.xticks(range(0, 25, 2))
    plt.yticks(range(len(unique_dates)), unique_dates, fontsize=8)
    plt.grid(axis="x", linestyle="--", alpha=0.3)
    plt.gca().invert_yaxis()

    area_handles = [
        plt.Line2D(
            [0], [0],
            marker="o",
            color="w",
            label=area,
            markerfacecolor=area_color_map[area],
            markersize=7,
        )
        for area in area_names
    ]
    plt.legend(handles=area_handles, title="Haifa Area", loc="upper right", fontsize=8)
    plt.tight_layout()
    plt.savefig(OUTPUT_AREA_TIMELINE, dpi=160)
    plt.close()

    print()
    print("Saved hour chart to:", OUTPUT_CHART)
    print("Saved date/hour heatmap to:", OUTPUT_HEATMAP)
    print("Saved daily timeline to:", OUTPUT_TIMELINE)
    print("Saved area hour chart to:", OUTPUT_AREA_HOURLY)
    print("Saved area timeline to:", OUTPUT_AREA_TIMELINE)

write_dashboard(area_hour_counts, OUTPUT_DASHBOARD)
print("Saved interactive dashboard to:", OUTPUT_DASHBOARD)
