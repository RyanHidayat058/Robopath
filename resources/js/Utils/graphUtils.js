/**
 * Robopath Graph & Path Planning Utilities
 */

export const ROBOT_COLORS = {
    1: '#0284c7', // Sky Blue (Alpha)
    2: '#8b5cf6', // Violet / Purple (Beta)
    3: '#f59e0b', // Amber / Golden Orange (Gamma)
    4: '#10b981', // Emerald
    5: '#ec4899', // Pink
};

export function getNode(locations, nameOrId, preferredFloor = null) {
    if (!nameOrId || !locations) return null;
    if (locations[nameOrId]) return nameOrId;

    const matches = [];
    for (const id in locations) {
        if (locations[id].name === nameOrId) {
            matches.push(id);
        }
    }

    if (matches.length === 1) return matches[0];
    if (matches.length > 1) {
        if (preferredFloor) {
            const match = matches.find((id) => Number(locations[id].floor) === Number(preferredFloor));
            if (match) return match;
        }
        return matches[0];
    }

    for (const id in locations) {
        if (locations[id].name && locations[id].name.toLowerCase() === String(nameOrId).toLowerCase()) {
            return id;
        }
    }
    return null;
}

export function getNodeCoordinates(locations, nodeId) {
    if (!nodeId || !locations || !locations[nodeId]) return { x: 50, y: 50, floor: 1 };
    return {
        x: locations[nodeId].x,
        y: locations[nodeId].y,
        floor: Number(locations[nodeId].floor || 1),
    };
}

export function findShortestPath(locations, adj, start, end) {
    if (!start || !end || !locations[start] || !locations[end]) return [];
    if (start === end) return [start];

    const queue = [[start]];
    const visited = new Set([start]);

    while (queue.length > 0) {
        const path = queue.shift();
        const current = path[path.length - 1];

        const neighbors = adj[current] || [];
        for (const neighbor of neighbors) {
            if (!visited.has(neighbor)) {
                visited.add(neighbor);
                const newPath = [...path, neighbor];
                if (neighbor === end) return newPath;
                queue.push(newPath);
            }
        }
    }
    return [];
}

export function resolveLocationNodeId(locations, x, y, floor = null) {
    let closestId = null;
    let minDst = Infinity;

    for (const id in locations) {
        const loc = locations[id];
        if (floor && Number(loc.floor) !== Number(floor)) continue;
        const dst = Math.hypot(loc.x - x, loc.y - y);
        if (dst < minDst) {
            minDst = dst;
            closestId = id;
        }
    }
    return closestId || (Number(floor) === 2 ? '2_Stairs' : '1_N7');
}

export function resolveLocationName(locations, x, y, floor = null) {
    const id = resolveLocationNodeId(locations, x, y, floor);
    if (id && locations[id]) {
        return locations[id].name || id;
    }
    return Number(floor) === 2 ? 'Lantai 2' : 'Lantai 1';
}

export function interpolate(p1, p2, ratio) {
    return {
        x: p1.x + (p2.x - p1.x) * ratio,
        y: p1.y + (p2.y - p1.y) * ratio,
    };
}

export function planRouteBetween(locations, adj, fromId, toId) {
    if (!locations[fromId] || !locations[toId]) return [];
    const f1 = Number(locations[fromId].floor || 1);
    const f2 = Number(locations[toId].floor || 1);

    if (f1 === f2) {
        const p = findShortestPath(locations, adj, fromId, toId);
        return [{ type: 'travel', floor: f1, path: p }];
    } else {
        const stairsFrom = f1 === 1 ? '1_Stairs' : '2_Stairs';
        const stairsTo = f2 === 1 ? '1_Stairs' : '2_Stairs';
        const p1 = findShortestPath(locations, adj, fromId, stairsFrom);
        const p2 = findShortestPath(locations, adj, stairsTo, toId);
        return [
            { type: 'travel', floor: f1, path: p1 },
            { type: 'stairs', fromFloor: f1, toFloor: f2, fromNode: stairsFrom, toNode: stairsTo, durationMs: 5500 },
            { type: 'travel', floor: f2, path: p2 },
        ];
    }
}

export function calculateMissionSchedule(delivery, locations, adj) {
    if (!delivery || !locations) return null;

    const startNode = getNode(locations, delivery.start_location);
    const destNode = getNode(locations, delivery.destination_location);
    if (!startNode || !destNode) return null;

    const route = planRouteBetween(locations, adj, startNode, destNode);
    const allPathNodes = [];
    route.forEach(st => {
        if (st.path) {
            st.path.forEach(n => {
                if (!allPathNodes.includes(n)) allPathNodes.push(n);
            });
        }
    });

    return {
        startNode,
        destNode,
        route,
        pathNodes: allPathNodes,
    };
}

export function sampleMissionPosition(delivery, locations, adj) {
    const schedule = calculateMissionSchedule(delivery, locations, adj);
    if (!schedule || schedule.pathNodes.length === 0) return null;

    const startLoc = locations[schedule.startNode];
    const destLoc = locations[schedule.destNode];
    if (!startLoc || !destLoc) return null;

    const elapsed = Date.now() - new Date(delivery.started_at || delivery.created_at).getTime();
    const duration = 28000; // Average simulated delivery duration
    const progress = Math.min(1, Math.max(0, (elapsed % duration) / duration));

    // Sample along pathNodes
    const nodes = schedule.pathNodes;
    if (nodes.length === 1) {
        const loc = locations[nodes[0]];
        return { x: loc.x, y: loc.y, floor: loc.floor || 1 };
    }

    const totalSegments = nodes.length - 1;
    const segIndex = Math.min(totalSegments - 1, Math.floor(progress * totalSegments));
    const segRatio = (progress * totalSegments) - segIndex;

    const n1 = locations[nodes[segIndex]];
    const n2 = locations[nodes[segIndex + 1]];
    if (!n1 || !n2) return { x: startLoc.x, y: startLoc.y, floor: startLoc.floor || 1 };

    return {
        x: n1.x + (n2.x - n1.x) * segRatio,
        y: n1.y + (n2.y - n1.y) * segRatio,
        floor: n1.floor || 1,
    };
}

export function getDeliveryMission(locations, adj, delivery, robot) {
    if (delivery._cachedMission) {
        return delivery._cachedMission;
    }

    const startNodeId = getNode(locations, delivery.start_location);
    const destNodeId = getNode(locations, delivery.destination_location);

    let originNodeId = getNode(locations, delivery.origin_location);
    if (!originNodeId && robot && robot.current_x && robot.current_y) {
        originNodeId = resolveLocationNodeId(locations, robot.current_x, robot.current_y, robot.floor || 1);
    }
    if (!originNodeId || !locations[originNodeId]) {
        originNodeId = '1_N7';
    }

    const validStart = startNodeId && locations[startNodeId] ? startNodeId : '1_Waiting Room';
    const validDest = destNodeId && locations[destNodeId] ? destNodeId : '2_Ruang Direktur';

    const pickupStage = {
        type: 'pickup',
        nodeId: validStart,
        floor: locations[validStart]?.floor || 1,
        durationMs: 2500,
    };

    const dropoffStage = {
        type: 'dropoff',
        nodeId: validDest,
        floor: locations[validDest]?.floor || 1,
        durationMs: 2500,
    };

    let rawStages = [];
    if (originNodeId !== validStart) {
        rawStages = [
            ...planRouteBetween(locations, adj, originNodeId, validStart),
            pickupStage,
            ...planRouteBetween(locations, adj, validStart, validDest),
            dropoffStage,
        ];
    } else {
        rawStages = [pickupStage, ...planRouteBetween(locations, adj, validStart, validDest), dropoffStage];
    }

    const consolidatedStages = [];
    for (const st of rawStages) {
        if (consolidatedStages.length > 0) {
            const prev = consolidatedStages[consolidatedStages.length - 1];
            if (prev.type === 'travel' && st.type === 'travel' && prev.floor === st.floor) {
                if (st.path && st.path.length > 0) {
                    prev.path = [...prev.path, ...st.path.slice(1)];
                }
                continue;
            }
        }
        consolidatedStages.push(st);
    }

    let totalTravelSegments = 0;
    consolidatedStages.forEach((st) => {
        if (st.type === 'travel') totalTravelSegments += Math.max(1, (st.path?.length || 1) - 1);
    });

    const baseTravelTimeMs = 26000;
    let accumulatedMs = 0;
    consolidatedStages.forEach((st) => {
        st.startMs = accumulatedMs;
        if (st.type === 'stairs') {
            st.durationMs = 5500;
        } else if (st.type === 'pickup' || st.type === 'dropoff') {
            st.durationMs = 2500;
        } else {
            const segCount = Math.max(1, (st.path?.length || 1) - 1);
            st.durationMs = Math.max(6000, Math.round(baseTravelTimeMs * (segCount / Math.max(1, totalTravelSegments))));
        }
        accumulatedMs += st.durationMs;
    });

    const mission = {
        originId: originNodeId,
        startId: validStart,
        destId: validDest,
        pickupStartMs: pickupStage.startMs,
        stages: consolidatedStages,
        totalDurationMs: accumulatedMs,
    };

    delivery._cachedMission = mission;
    return mission;
}

export function buildReturnMission(locations, adj, robot, now = new Date()) {
    const currentLocId = resolveLocationNodeId(locations, robot.current_x, robot.current_y, robot.floor || 1);
    const targetId = '1_N7';
    if (!currentLocId || currentLocId === targetId) return null;

    const rawStages = planRouteBetween(locations, adj, currentLocId, targetId);
    if (!rawStages || rawStages.length === 0) return null;

    const consolidatedStages = [];
    for (const st of rawStages) {
        if (consolidatedStages.length > 0) {
            const prev = consolidatedStages[consolidatedStages.length - 1];
            if (prev.type === 'travel' && st.type === 'travel' && prev.floor === st.floor) {
                if (st.path && st.path.length > 0) {
                    prev.path = [...prev.path, ...st.path.slice(1)];
                }
                continue;
            }
        }
        consolidatedStages.push(st);
    }

    let totalTravelSegments = 0;
    consolidatedStages.forEach((st) => {
        if (st.type === 'travel') totalTravelSegments += Math.max(1, (st.path?.length || 1) - 1);
    });

    const baseTravelTimeMs = 24000;
    let accumulatedMs = 0;
    consolidatedStages.forEach((st) => {
        st.startMs = accumulatedMs;
        if (st.type === 'stairs') {
            st.durationMs = 5500;
        } else {
            const segCount = Math.max(1, (st.path?.length || 1) - 1);
            st.durationMs = Math.max(5000, Math.round(baseTravelTimeMs * (segCount / Math.max(1, totalTravelSegments))));
        }
        accumulatedMs += st.durationMs;
    });

    return {
        originId: currentLocId,
        destId: targetId,
        stages: consolidatedStages,
        totalDurationMs: accumulatedMs,
        startedAt: now.getTime() + 1500,
    };
}
