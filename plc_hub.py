import argparse
import csv
import json
import math
import os
import time
from dataclasses import dataclass
from datetime import date, datetime
from pathlib import Path
from typing import Any, Dict, List, Optional, Sequence, Set, Tuple

from pymodbus.client import ModbusTcpClient

import kepek_reader
import un1_reader
import un2_reader


@dataclass
class DailyKgTracker:
    day: date
    hourly_totals: Dict[int, float]
    active_hours: Set[int]
    active_runtime_seconds: float


@dataclass
class TransferCycleState:
    day: date
    was_active: bool
    stop_started_at: Optional[datetime]
    cycle_started_at: Optional[datetime]
    last_drop_residual_kg: Optional[float]
    last_cycle_ended_at: Optional[datetime]
    cycle_count_today: int


@dataclass
class PlcConfig:
    key: str
    title: str
    ip: str
    port: int
    slave: int
    timeout: float
    addr_shift: int
    tags: Sequence[Any]
    kg_tag: str
    flow_tag: str
    flow_max: float
    csv_path: Path
    daily_report_path: Path


@dataclass
class PlcRuntime:
    config: PlcConfig
    client: ModbusTcpClient
    holding_blocks: List[Tuple[int, int]]
    coil_blocks: List[Tuple[int, int]]
    tracker: DailyKgTracker
    last_values: Dict[str, float]
    cycle_state: TransferCycleState


def parse_args() -> argparse.Namespace:
    parser = argparse.ArgumentParser(
        description="Read UN1/UN2/KEPEK PLCs and publish live JSON for PHP"
    )
    parser.add_argument("--interval", type=float, default=1.0, help="Polling interval in seconds")
    parser.add_argument("--port", type=int, default=502, help="Modbus TCP port")
    parser.add_argument("--slave", type=int, default=1, help="Modbus slave/unit id")
    parser.add_argument("--timeout", type=float, default=3.0, help="Modbus timeout in seconds")
    parser.add_argument("--addr-shift", type=int, default=0, help="Address shift for all PLCs")
    parser.add_argument("--work-threshold", type=float, default=0.1, help="KG threshold for active runtime")
    parser.add_argument("--kg-max", type=float, default=100000.0, help="Ignore KG above this value")
    parser.add_argument("--flow-max", type=float, default=30000.0, help="Ignore AKIS above this value")
    parser.add_argument("--flow-active-threshold", type=float, default=3.0, help="Flow threshold to mark transfer active")
    parser.add_argument("--stop-hold-seconds", type=float, default=4.0, help="Flow must stay below threshold this long to close a cycle")

    parser.add_argument("--un1-ip", default="192.168.20.103", help="UN1 PLC IP")
    parser.add_argument("--un2-ip", default="192.168.20.104", help="UN2 PLC IP")
    parser.add_argument("--kepek-ip", default="192.168.20.105", help="KEPEK PLC IP")

    parser.add_argument("--output-json", default="php_data/live_data.json", help="Live JSON output path")
    parser.add_argument("--no-unit-csv", action="store_true", help="Disable per-cycle unit CSV append")
    parser.add_argument("--no-clear", action="store_true", help="Do not clear terminal each cycle")
    return parser.parse_args()


def clear_screen(no_clear: bool) -> None:
    if no_clear:
        return
    os.system("cls" if os.name == "nt" else "clear")


def init_tracker(day_value: date) -> DailyKgTracker:
    return DailyKgTracker(
        day=day_value,
        hourly_totals={hour: 0.0 for hour in range(24)},
        active_hours=set(),
        active_runtime_seconds=0.0,
    )


def init_cycle_state(day_value: date) -> TransferCycleState:
    return TransferCycleState(
        day=day_value,
        was_active=False,
        stop_started_at=None,
        cycle_started_at=None,
        last_drop_residual_kg=None,
        last_cycle_ended_at=None,
        cycle_count_today=0,
    )


def ensure_unit_csv_header(csv_path: Path, tags: Sequence[Any]) -> None:
    csv_path.parent.mkdir(parents=True, exist_ok=True)
    if csv_path.exists() and csv_path.stat().st_size > 0:
        return

    with csv_path.open("a", newline="", encoding="utf-8") as handle:
        writer = csv.writer(handle)
        writer.writerow(["timestamp", *[tag.name for tag in tags]])


def append_unit_csv_row(
    csv_path: Path,
    timestamp: datetime,
    tags: Sequence[Any],
    decoded: Dict[str, Optional[float]],
) -> None:
    row: List[object] = [timestamp.isoformat(timespec="seconds")]
    for tag in tags:
        value = decoded.get(tag.name)
        row.append("ERR" if value is None else value)

    with csv_path.open("a", newline="", encoding="utf-8") as handle:
        writer = csv.writer(handle)
        writer.writerow(row)


def ensure_daily_report_header(report_path: Path) -> None:
    report_path.parent.mkdir(parents=True, exist_ok=True)
    if report_path.exists() and report_path.stat().st_size > 0:
        return

    with report_path.open("a", newline="", encoding="utf-8") as handle:
        writer = csv.writer(handle)
        writer.writerow(
            [
                "report_day",
                "closed_at",
                "close_reason",
                "kg_total_sum",
                "active_hours_count",
                "active_runtime_hours",
                "hourly_breakdown",
            ]
        )


def format_hourly_breakdown(hourly_totals: Dict[int, float]) -> str:
    return " | ".join(f"{hour:02d}:{hourly_totals.get(hour, 0.0):.3f}" for hour in range(24))


def append_daily_report_row(
    report_path: Path,
    tracker: DailyKgTracker,
    closed_at: datetime,
    close_reason: str,
) -> None:
    kg_total_sum = sum(tracker.hourly_totals.values())
    active_runtime_hours = tracker.active_runtime_seconds / 3600.0

    with report_path.open("a", newline="", encoding="utf-8") as handle:
        writer = csv.writer(handle)
        writer.writerow(
            [
                tracker.day.isoformat(),
                closed_at.isoformat(timespec="seconds"),
                close_reason,
                round(kg_total_sum, 6),
                len(tracker.active_hours),
                round(active_runtime_hours, 6),
                format_hourly_breakdown(tracker.hourly_totals),
            ]
        )


def sanitize_value(value: Optional[float]) -> Optional[float]:
    if value is None:
        return None
    try:
        numeric = float(value)
    except (TypeError, ValueError):
        return None

    if not math.isfinite(numeric):
        return None
    return numeric


def stabilize_kg_value(
    plc_name: str,
    tag_name: str,
    raw_value: Optional[float],
    previous_value: Optional[float],
    work_threshold: float,
    kg_max: float,
) -> Tuple[Optional[float], Optional[str]]:
    if raw_value is None:
        return None, None

    if raw_value < 0 or raw_value > kg_max:
        if previous_value is not None:
            return previous_value, f"{plc_name}:{tag_name}:kg-outlier"
        return None, f"{plc_name}:{tag_name}:kg-outlier"

    if previous_value is None:
        return raw_value, None

    # Sudden unrealistic jump guard (ex: 5kg -> millions in a single cycle).
    if previous_value >= work_threshold and raw_value > previous_value * 8 and raw_value > work_threshold * 20:
        return previous_value, f"{plc_name}:{tag_name}:kg-spike-filtered"

    return raw_value, None


def stabilize_flow_value(
    plc_name: str,
    tag_name: str,
    raw_value: Optional[float],
    previous_value: Optional[float],
    flow_max: float,
) -> Tuple[Optional[float], Optional[str]]:
    if raw_value is None:
        return None, None

    if raw_value < 0 or raw_value > flow_max:
        if previous_value is not None:
            return previous_value, f"{plc_name}:{tag_name}:flow-outlier"
        return None, f"{plc_name}:{tag_name}:flow-outlier"

    if previous_value is None:
        return raw_value, None

    # Filter sudden unrealistic upward jumps while allowing normal stop-to-zero transitions.
    if previous_value > 0.1 and raw_value > previous_value * 6 and raw_value > 5:
        return previous_value, f"{plc_name}:{tag_name}:flow-spike-filtered"

    return raw_value, None


def update_tracker(
    tracker: DailyKgTracker,
    timestamp: datetime,
    flow_value: Optional[float],
    interval_seconds: float,
    flow_active_threshold: float,
    flow_max: float,
) -> None:
    safe_flow = sanitize_value(flow_value)
    if safe_flow is None:
        return

    if safe_flow < 0 or safe_flow > flow_max:
        return

    # AKIS is kg/hour. Integrate over polling interval to get kg increment.
    kg_increment = safe_flow * (max(0.0, interval_seconds) / 3600.0)
    if kg_increment > 0:
        tracker.hourly_totals[timestamp.hour] = tracker.hourly_totals.get(timestamp.hour, 0.0) + kg_increment

    if safe_flow >= max(0.0, flow_active_threshold):
        tracker.active_hours.add(timestamp.hour)
        tracker.active_runtime_seconds += max(0.0, interval_seconds)


def update_transfer_cycle(
    cycle_state: TransferCycleState,
    timestamp: datetime,
    flow_value: Optional[float],
    kg_value: Optional[float],
    flow_active_threshold: float,
    stop_hold_seconds: float,
    kg_max: float,
) -> None:
    safe_flow = sanitize_value(flow_value)
    safe_kg = sanitize_value(kg_value)

    is_active = safe_flow is not None and safe_flow >= max(0.0, flow_active_threshold)

    if is_active:
        if not cycle_state.was_active:
            cycle_state.cycle_started_at = timestamp
        cycle_state.was_active = True
        cycle_state.stop_started_at = None
        return

    if not cycle_state.was_active:
        return

    if cycle_state.stop_started_at is None:
        cycle_state.stop_started_at = timestamp
        return

    stopped_for = (timestamp - cycle_state.stop_started_at).total_seconds()
    if stopped_for < max(0.0, stop_hold_seconds):
        return

    residual = None
    if safe_kg is not None and 0 <= safe_kg <= kg_max:
        residual = safe_kg

    cycle_state.last_drop_residual_kg = residual
    cycle_state.last_cycle_ended_at = timestamp
    cycle_state.cycle_count_today += 1
    cycle_state.was_active = False
    cycle_state.stop_started_at = None
    cycle_state.cycle_started_at = None


def write_json_atomic(path: Path, payload: Dict[str, Any]) -> None:
    path.parent.mkdir(parents=True, exist_ok=True)
    tmp_path = path.with_suffix(path.suffix + ".tmp")
    text = json.dumps(payload, ensure_ascii=False, separators=(",", ":"))
    tmp_path.write_text(text, encoding="utf-8")
    tmp_path.replace(path)


def make_configs(args: argparse.Namespace) -> List[PlcConfig]:
    return [
        PlcConfig(
            key="un1",
            title="UN1",
            ip=args.un1_ip,
            port=args.port,
            slave=args.slave,
            timeout=args.timeout,
            addr_shift=args.addr_shift,
            tags=un1_reader.TAGS,
            kg_tag="UN1_KG",
            flow_tag="UN1_AKIS",
            flow_max=args.flow_max,
            csv_path=Path("un1_log.csv"),
            daily_report_path=Path("un1_daily_report.csv"),
        ),
        PlcConfig(
            key="un2",
            title="UN2",
            ip=args.un2_ip,
            port=args.port,
            slave=args.slave,
            timeout=args.timeout,
            addr_shift=args.addr_shift,
            tags=un2_reader.TAGS,
            kg_tag="UN2_KG",
            flow_tag="UN2_AKIS",
            flow_max=args.flow_max,
            csv_path=Path("un2_log.csv"),
            daily_report_path=Path("un2_daily_report.csv"),
        ),
        PlcConfig(
            key="kepek",
            title="KEPEK",
            ip=args.kepek_ip,
            port=args.port,
            slave=args.slave,
            timeout=args.timeout,
            addr_shift=args.addr_shift,
            tags=kepek_reader.TAGS,
            kg_tag="KEPEK_KG",
            flow_tag="KEPEK_AKIS",
            flow_max=args.flow_max,
            csv_path=Path("kepek_log.csv"),
            daily_report_path=Path("kepek_daily_report.csv"),
        ),
    ]


def make_runtime(config: PlcConfig) -> PlcRuntime:
    holding_blocks = un1_reader.build_read_blocks(
        config.tags,
        source="holding",
        addr_shift=config.addr_shift,
        max_span=120,
        max_gap=8,
    )
    coil_blocks = un1_reader.build_read_blocks(
        config.tags,
        source="coil",
        addr_shift=config.addr_shift,
        max_span=256,
        max_gap=16,
    )

    client = ModbusTcpClient(host=config.ip, port=config.port, timeout=config.timeout)
    tracker = init_tracker(datetime.now().date())

    ensure_daily_report_header(config.daily_report_path)
    ensure_unit_csv_header(config.csv_path, config.tags)

    return PlcRuntime(
        config=config,
        client=client,
        holding_blocks=holding_blocks,
        coil_blocks=coil_blocks,
        tracker=tracker,
        last_values={},
        cycle_state=init_cycle_state(datetime.now().date()),
    )


def poll_one(
    runtime: PlcRuntime,
    now: datetime,
    interval_seconds: float,
    work_threshold: float,
    kg_max: float,
    flow_active_threshold: float,
    stop_hold_seconds: float,
    write_unit_csv: bool,
) -> Dict[str, Any]:
    if now.date() != runtime.tracker.day:
        append_daily_report_row(
            runtime.config.daily_report_path,
            tracker=runtime.tracker,
            closed_at=now,
            close_reason="day-rollover",
        )
        runtime.tracker = init_tracker(now.date())
    if now.date() != runtime.cycle_state.day:
        runtime.cycle_state = init_cycle_state(now.date())

    decoded: Dict[str, Optional[float]] = {tag.name: None for tag in runtime.config.tags}
    decode_errors: Dict[str, str] = {}
    read_errors: List[str] = []

    if not runtime.client.connect():
        read_errors.append(
            f"connect-failed:{runtime.config.ip}:{runtime.config.port}:slave={runtime.config.slave}"
        )
    else:
        try:
            holding_values, holding_errors = un1_reader.read_holding_blocks(
                runtime.client,
                blocks=runtime.holding_blocks,
                slave=runtime.config.slave,
            )
            coil_values, coil_errors = un1_reader.read_coil_blocks(
                runtime.client,
                blocks=runtime.coil_blocks,
                slave=runtime.config.slave,
            )
            read_errors.extend(holding_errors)
            read_errors.extend(coil_errors)

            for tag in runtime.config.tags:
                value, error = un1_reader.decode_tag(
                    tag,
                    holding_values=holding_values,
                    coil_values=coil_values,
                    addr_shift=runtime.config.addr_shift,
                )
                clean_value = sanitize_value(value)
                if tag.name == runtime.config.kg_tag:
                    previous = runtime.last_values.get(tag.name)
                    clean_value, filtered_error = stabilize_kg_value(
                        plc_name=runtime.config.title,
                        tag_name=tag.name,
                        raw_value=clean_value,
                        previous_value=previous,
                        work_threshold=work_threshold,
                        kg_max=kg_max,
                    )
                    if filtered_error is not None:
                        decode_errors[tag.name] = filtered_error
                    if clean_value is not None:
                        runtime.last_values[tag.name] = clean_value
                elif tag.name == runtime.config.flow_tag:
                    previous = runtime.last_values.get(tag.name)
                    clean_value, filtered_error = stabilize_flow_value(
                        plc_name=runtime.config.title,
                        tag_name=tag.name,
                        raw_value=clean_value,
                        previous_value=previous,
                        flow_max=runtime.config.flow_max,
                    )
                    if filtered_error is not None:
                        decode_errors[tag.name] = filtered_error
                    if clean_value is not None:
                        runtime.last_values[tag.name] = clean_value
                decoded[tag.name] = clean_value
                if error is not None and tag.name not in decode_errors:
                    decode_errors[tag.name] = error
        except Exception as exc:
            read_errors.append(f"poll-exception:{exc}")

    if write_unit_csv:
        append_unit_csv_row(runtime.config.csv_path, now, runtime.config.tags, decoded)

    update_tracker(
        runtime.tracker,
        timestamp=now,
        flow_value=decoded.get(runtime.config.flow_tag),
        interval_seconds=interval_seconds,
        flow_active_threshold=flow_active_threshold,
        flow_max=runtime.config.flow_max,
    )
    update_transfer_cycle(
        runtime.cycle_state,
        timestamp=now,
        flow_value=decoded.get(runtime.config.flow_tag),
        kg_value=decoded.get(runtime.config.kg_tag),
        flow_active_threshold=flow_active_threshold,
        stop_hold_seconds=stop_hold_seconds,
        kg_max=kg_max,
    )

    daily_total = sum(runtime.tracker.hourly_totals.values())
    this_hour = runtime.tracker.hourly_totals.get(now.hour, 0.0)
    runtime_hours = runtime.tracker.active_runtime_seconds / 3600.0

    if read_errors:
        status = "error"
    elif decode_errors:
        status = "partial"
    else:
        status = "ok"

    return {
        "name": runtime.config.title,
        "ip": runtime.config.ip,
        "timestamp": now.isoformat(timespec="seconds"),
        "status": status,
        "kg_summary": {
            "this_hour": round(this_hour, 6),
            "today": round(daily_total, 6),
            "active_hours_count": len(runtime.tracker.active_hours),
            "active_runtime_hours": round(runtime_hours, 6),
            "cycle_count_today": runtime.cycle_state.cycle_count_today,
            "last_drop_residual_kg": runtime.cycle_state.last_drop_residual_kg,
            "last_cycle_ended_at": (
                runtime.cycle_state.last_cycle_ended_at.isoformat(timespec="seconds")
                if runtime.cycle_state.last_cycle_ended_at is not None
                else None
            ),
            "is_transfer_active": runtime.cycle_state.was_active,
        },
        "values": decoded,
        "read_errors": read_errors,
        "decode_errors": decode_errors,
    }


def render_console(args: argparse.Namespace, snapshot: Dict[str, Any]) -> None:
    clear_screen(args.no_clear)
    print("=" * 98)
    print("PLC HUB -> PHP LIVE BRIDGE")
    print("=" * 98)
    print(
        f"Time: {snapshot['generated_at']} | Interval: {args.interval}s | "
        f"Output: {Path(args.output_json).resolve()}"
    )
    print("-" * 98)
    print(f"{'PLC':8} {'STATUS':8} {'IP':16} {'KG_THIS_HOUR':14} {'KG_TODAY':14} {'ACTIVE_HOURS':13}")
    print("-" * 98)

    for key in ["un1", "un2", "kepek"]:
        plc = snapshot["plcs"].get(key, {})
        kg = plc.get("kg_summary", {})
        print(
            f"{plc.get('name', key):8} {plc.get('status', 'na'):8} {plc.get('ip', '-'):16} "
            f"{kg.get('this_hour', 0.0):14.3f} {kg.get('today', 0.0):14.3f} {kg.get('active_hours_count', 0):13}"
        )

    print("-" * 98)
    print("Ctrl+C ile durdurabilirsiniz.")


def close_all(runtimes: Sequence[PlcRuntime]) -> None:
    closed_at = datetime.now()
    for runtime in runtimes:
        try:
            append_daily_report_row(
                runtime.config.daily_report_path,
                tracker=runtime.tracker,
                closed_at=closed_at,
                close_reason="shutdown",
            )
        except Exception:
            pass

        try:
            runtime.client.close()
        except Exception:
            pass


def run() -> None:
    args = parse_args()
    configs = make_configs(args)
    runtimes = [make_runtime(config) for config in configs]

    output_json = Path(args.output_json)

    try:
        while True:
            started = time.perf_counter()
            now = datetime.now()

            snapshot: Dict[str, Any] = {
                "generated_at": now.isoformat(timespec="seconds"),
                "interval_seconds": args.interval,
                "source": "plc_hub.py",
                "plcs": {},
            }

            for runtime in runtimes:
                snapshot["plcs"][runtime.config.key] = poll_one(
                    runtime,
                    now=now,
                    interval_seconds=args.interval,
                    work_threshold=args.work_threshold,
                    kg_max=args.kg_max,
                    flow_active_threshold=args.flow_active_threshold,
                    stop_hold_seconds=args.stop_hold_seconds,
                    write_unit_csv=not args.no_unit_csv,
                )

            write_json_atomic(output_json, snapshot)
            render_console(args, snapshot)

            elapsed = time.perf_counter() - started
            sleep_for = max(0.0, args.interval - elapsed)
            time.sleep(sleep_for)
    finally:
        close_all(runtimes)


if __name__ == "__main__":
    try:
        run()
    except KeyboardInterrupt:
        print("\nPLC hub stopped by user.")
