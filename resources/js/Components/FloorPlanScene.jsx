import React, { useRef, useEffect, useState, useMemo } from 'react';
import * as THREE from 'three';
import { OrbitControls } from 'three/examples/jsm/controls/OrbitControls.js';
import { GLTFLoader } from 'three/examples/jsm/loaders/GLTFLoader.js';
import { DRACOLoader } from 'three/examples/jsm/loaders/DRACOLoader.js';
import gsap from 'gsap';
import { Layers, Eye, RefreshCw, ZoomIn, ZoomOut, Compass, Navigation } from 'lucide-react';

/**
 * Enhanced Modern 3D FloorPlan Telemetry Scene
 * - Blue & White Theme
 * - Room Billboard Sprite Labels for Lantai 1 & Lantai 2
 * - Dynamic Closer Camera Focus
 * - Realistic Shading, Ambient Occlusion, and Shadows
 */
export default function FloorPlanScene({
    activeFloor = 1,
    onFloorChange,
    robots = [],
    activeDeliveries = [],
    locations = {},
    selectedRobotId = null,
    onSelectRobot = null,
    onSelectRoom = null,
    enableControls = true,
    className = '',
}) {
    const containerRef = useRef(null);
    const sceneRef = useRef(null);
    const rendererRef = useRef(null);
    const cameraRef = useRef(null);
    const controlsRef = useRef(null);
    const modelGroupRef = useRef(null);
    const roomLabelsGroupRef = useRef(null);
    const robotMeshesRef = useRef({});
    const pathsGroupRef = useRef(null);
    const animationFrameIdRef = useRef(null);

    const [isLoading, setIsLoading] = useState(true);
    const [loadProgress, setLoadProgress] = useState(0);
    const [hoveredRoom, setHoveredRoom] = useState(null);
    const [showRoomLabels, setShowRoomLabels] = useState(true);

    // Map 2D graph coordinates (0..100) to 3D world space
    const coordTo3D = (x, y, floor = 1) => {
        const scaleX = 0.55;
        const scaleZ = 0.55;
        const offsetX = -28;
        const offsetZ = -32;
        const posX = (x - 50) * scaleX + offsetX;
        const posZ = (y - 50) * scaleZ + offsetZ;
        const posY = floor === 2 ? 4.2 : 0.2;
        return new THREE.Vector3(posX, posY, posZ);
    };

    // Helper: Create High-DPI Crisp Billboard Text Texture for Room Labels
    const createRoomSprite = (text, floor) => {
        const canvas = document.createElement('canvas');
        canvas.width = 512;
        canvas.height = 160;
        const ctx = canvas.getContext('2d');

        // Draw pill background with border
        const r = 24;
        const x = 16, y = 16, w = 480, h = 128;
        
        ctx.beginPath();
        ctx.moveTo(x + r, y);
        ctx.lineTo(x + w - r, y);
        ctx.quadraticCurveTo(x + w, y, x + w, y + r);
        ctx.lineTo(x + w, y + h - r);
        ctx.quadraticCurveTo(x + w, y + h, x + w - r, y + h);
        ctx.lineTo(x + r, y + h);
        ctx.quadraticCurveTo(x, y + h, x, y + h - r);
        ctx.lineTo(x, y + r);
        ctx.quadraticCurveTo(x, y, x + r, y);
        ctx.closePath();

        // Theme Blue & Clean Glass styling
        ctx.fillStyle = 'rgba(15, 23, 42, 0.88)';
        ctx.fill();
        ctx.lineWidth = 6;
        ctx.strokeStyle = '#3b82f6'; // Bright Electric Blue
        ctx.stroke();

        // Little status dot
        ctx.beginPath();
        ctx.arc(x + 36, y + h / 2, 10, 0, Math.PI * 2);
        ctx.fillStyle = '#60a5fa';
        ctx.fill();

        // Text
        ctx.fillStyle = '#ffffff';
        ctx.font = 'bold 36px "Inter", -apple-system, sans-serif';
        ctx.textAlign = 'left';
        ctx.textBaseline = 'middle';
        
        // Truncate if too long
        let displayStr = text.replace(/^(1_|2_)/, '');
        if (displayStr.length > 18) {
            displayStr = displayStr.substring(0, 16) + '...';
        }
        ctx.fillText(displayStr, x + 60, y + h / 2);

        const texture = new THREE.CanvasTexture(canvas);
        texture.minFilter = THREE.LinearFilter;
        texture.generateMipmaps = false;

        const spriteMat = new THREE.SpriteMaterial({
            map: texture,
            transparent: true,
            depthTest: false,
            depthWrite: false,
        });

        const sprite = new THREE.Sprite(spriteMat);
        sprite.scale.set(4.8, 1.5, 1);
        return sprite;
    };

    // Initialize Three.js Scene
    useEffect(() => {
        if (!containerRef.current) return;

        const container = containerRef.current;
        const width = container.clientWidth || 800;
        const height = container.clientHeight || 500;

        // Scene with Modern Deep Tech Blue / Slate background
        const scene = new THREE.Scene();
        scene.background = new THREE.Color('#0b1329'); // Elegant Deep Blue / Slate
        scene.fog = new THREE.FogExp2('#0b1329', 0.012);
        sceneRef.current = scene;

        // Camera - Focused much closer to the building model
        // Previous was far away (0, 26, 32). Closer: (0, 16, 20) with narrower FOV
        const camera = new THREE.PerspectiveCamera(36, width / height, 0.1, 1000);
        camera.position.set(0, 17, 21);
        cameraRef.current = camera;

        // Renderer
        const renderer = new THREE.WebGLRenderer({ antialias: true, alpha: false, powerPreference: 'high-performance' });
        renderer.setSize(width, height);
        renderer.setPixelRatio(Math.min(window.devicePixelRatio, 2));
        renderer.toneMapping = THREE.ACESFilmicToneMapping;
        renderer.toneMappingExposure = 1.25;
        renderer.shadowMap.enabled = true;
        renderer.shadowMap.type = THREE.PCFSoftShadowMap;
        container.appendChild(renderer.domElement);
        rendererRef.current = renderer;

        // OrbitControls
        const controls = new OrbitControls(camera, renderer.domElement);
        controls.enableDamping = true;
        controls.dampingFactor = 0.06;
        controls.maxPolarAngle = Math.PI / 2 - 0.08;
        controls.minDistance = 6;
        controls.maxDistance = 65;
        controls.target.set(0, 0, 0);
        controlsRef.current = controls;

        // Professional Clean Blue & Neutral Lighting
        const ambientLight = new THREE.AmbientLight('#e0e7ff', 1.8);
        scene.add(ambientLight);

        const mainSunLight = new THREE.DirectionalLight('#ffffff', 2.6);
        mainSunLight.position.set(30, 45, 25);
        mainSunLight.castShadow = true;
        mainSunLight.shadow.mapSize.width = 2048;
        mainSunLight.shadow.mapSize.height = 2048;
        mainSunLight.shadow.bias = -0.0005;
        scene.add(mainSunLight);

        const blueFillLight = new THREE.DirectionalLight('#38bdf8', 1.2);
        blueFillLight.position.set(-25, 30, -30);
        scene.add(blueFillLight);

        // Ground Grid Plane (Vibrant Tech Blue Grid)
        const grid = new THREE.GridHelper(90, 45, '#2563eb', '#1e293b');
        grid.position.y = -0.05;
        scene.add(grid);

        // Paths group for dynamic line visualization
        const pathsGroup = new THREE.Group();
        scene.add(pathsGroup);
        pathsGroupRef.current = pathsGroup;

        // Room Labels Group
        const roomLabelsGroup = new THREE.Group();
        scene.add(roomLabelsGroup);
        roomLabelsGroupRef.current = roomLabelsGroup;

        // Model Group
        const modelGroup = new THREE.Group();
        scene.add(modelGroup);
        modelGroupRef.current = modelGroup;

        // Setup Draco & GLTF Loader
        const dracoLoader = new DRACOLoader();
        dracoLoader.setDecoderPath('/draco/');
        dracoLoader.setDecoderConfig({ type: 'js' });

        const gltfLoader = new GLTFLoader();
        gltfLoader.setDRACOLoader(dracoLoader);

        gltfLoader.load(
            '/models/Denah3D.glb',
            (gltf) => {
                const model = gltf.scene;

                // Center model accurately
                const bbox = new THREE.Box3().setFromObject(model);
                const center = bbox.getCenter(new THREE.Vector3());
                model.position.x -= center.x;
                model.position.y -= bbox.min.y;
                model.position.z -= center.z;

                // Optimize materials with modern crisp contrast
                model.traverse((child) => {
                    if (child.isMesh) {
                        child.castShadow = true;
                        child.receiveShadow = true;
                        if (child.material) {
                            child.material.roughness = THREE.MathUtils.clamp(child.material.roughness, 0.35, 0.85);
                            child.material.metalness = THREE.MathUtils.clamp(child.material.metalness || 0.1, 0.05, 0.5);
                        }
                    }
                });

                modelGroup.add(model);
                setIsLoading(false);

                // Initialize floor visibility & focus
                updateFloorVisibility(activeFloor, modelGroup);
                focusFloorCamera(activeFloor, false);
            },
            (xhr) => {
                if (xhr.total > 0) {
                    setLoadProgress(Math.round((xhr.loaded / xhr.total) * 100));
                }
            },
            (error) => {
                console.error('Error loading 3D floorplan model:', error);
                setIsLoading(false);
            }
        );

        // Animation Loop
        const clock = new THREE.Clock();
        const animate = () => {
            animationFrameIdRef.current = requestAnimationFrame(animate);
            controls.update();
            renderer.render(scene, camera);
        };
        animate();

        // Handle Resize
        const handleResize = () => {
            if (!containerRef.current || !renderer || !camera) return;
            const newWidth = containerRef.current.clientWidth;
            const newHeight = containerRef.current.clientHeight;
            camera.aspect = newWidth / newHeight;
            camera.updateProjectionMatrix();
            renderer.setSize(newWidth, newHeight);
        };
        window.addEventListener('resize', handleResize);

        // Raycasting for room interaction
        const raycaster = new THREE.Raycaster();
        const mouse = new THREE.Vector2();

        const onPointerMove = (e) => {
            const rect = renderer.domElement.getBoundingClientRect();
            mouse.x = ((e.clientX - rect.left) / rect.width) * 2 - 1;
            mouse.y = -((e.clientY - rect.top) / rect.height) * 2 + 1;

            raycaster.setFromCamera(mouse, camera);
            if (modelGroupRef.current) {
                const intersects = raycaster.intersectObjects(modelGroupRef.current.children, true);
                if (intersects.length > 0) {
                    let hitNode = intersects[0].object;
                    while (hitNode && hitNode.parent && hitNode.parent !== modelGroupRef.current) {
                        if (hitNode.name && (hitNode.name.includes('LANTAI') || hitNode.name.includes('OFFICE'))) {
                            break;
                        }
                        hitNode = hitNode.parent;
                    }
                    if (hitNode && hitNode.name) {
                        setHoveredRoom(hitNode.name);
                        return;
                    }
                }
            }
            setHoveredRoom(null);
        };

        const onClick = () => {
            if (hoveredRoom && onSelectRoom) {
                onSelectRoom(hoveredRoom);
            }
        };

        renderer.domElement.addEventListener('pointermove', onPointerMove);
        renderer.domElement.addEventListener('click', onClick);

        return () => {
            window.removeEventListener('resize', handleResize);
            if (renderer.domElement) {
                renderer.domElement.removeEventListener('pointermove', onPointerMove);
                renderer.domElement.removeEventListener('click', onClick);
            }
            if (animationFrameIdRef.current) {
                cancelAnimationFrame(animationFrameIdRef.current);
            }
            renderer.dispose();
            dracoLoader.dispose();
            if (container.contains(renderer.domElement)) {
                container.removeChild(renderer.domElement);
            }
        };
    }, []);

    // Rebuild & render 3D Room Name Sprites when locations or floor changes
    useEffect(() => {
        if (!roomLabelsGroupRef.current) return;
        const group = roomLabelsGroupRef.current;

        // Clear existing sprites
        while (group.children.length > 0) {
            const child = group.children[0];
            if (child.material && child.material.map) {
                child.material.map.dispose();
                child.material.dispose();
            }
            group.remove(child);
        }

        if (!showRoomLabels) return;

        // Populate room sprites for current active floor
        const locList = Object.values(locations || {});
        const floorRooms = locList.filter(
            (loc) => (Number(loc.floor) || 1) === activeFloor && (loc.is_destination || !loc.hidden)
        );

        floorRooms.forEach((room) => {
            const sprite = createRoomSprite(room.name || room.id, activeFloor);
            const pos = coordTo3D(room.x, room.y, activeFloor);
            // Float slightly above floor elements
            sprite.position.set(pos.x, pos.y + (activeFloor === 2 ? 1.4 : 1.2), pos.z);
            group.add(sprite);
        });
    }, [locations, activeFloor, showRoomLabels]);

    // Option C: Toggle floor node visibility
    const updateFloorVisibility = (floor, rootGroup) => {
        const root = rootGroup || modelGroupRef.current;
        if (!root) return;

        root.traverse((node) => {
            const name = node.name || '';
            const isFloor1 = name.includes('LANTAI1') || name.includes('OFFICE_LANTAI_1');
            const isFloor2 = name.includes('LANTAI2') || name.includes('OFFICE_LANTAI_2') || name.includes('OFFICE_ATAP');

            if (floor === 1) {
                if (isFloor2) node.visible = false;
                else if (isFloor1) node.visible = true;
            } else if (floor === 2) {
                if (isFloor1) node.visible = false;
                else if (isFloor2) node.visible = true;
            }
        });
    };

    // Camera Focus Transition (Tuned to be MUCH closer and focused on active floor)
    const focusFloorCamera = (floor, animate = true) => {
        if (!cameraRef.current || !controlsRef.current) return;

        const targetPos = floor === 2 ? new THREE.Vector3(0, 3.8, 0) : new THREE.Vector3(0, 0.4, 0);
        const cameraPos = floor === 2 ? new THREE.Vector3(0, 18.5, 20.5) : new THREE.Vector3(0, 15.5, 21.5);

        if (!animate) {
            controlsRef.current.target.copy(targetPos);
            cameraRef.current.position.copy(cameraPos);
            controlsRef.current.update();
            return;
        }

        gsap.to(controlsRef.current.target, {
            x: targetPos.x,
            y: targetPos.y,
            z: targetPos.z,
            duration: 0.8,
            ease: 'power2.out',
            onUpdate: () => controlsRef.current.update(),
        });

        gsap.to(cameraRef.current.position, {
            x: cameraPos.x,
            y: cameraPos.y,
            z: cameraPos.z,
            duration: 0.8,
            ease: 'power2.out',
        });
    };

    // React to floor changes
    useEffect(() => {
        if (!modelGroupRef.current) return;
        updateFloorVisibility(activeFloor);
        focusFloorCamera(activeFloor, true);
    }, [activeFloor]);

    // Update 3D Robot Models & Positions (Tech Blue Styling)
    useEffect(() => {
        if (!sceneRef.current) return;
        const scene = sceneRef.current;

        robots.forEach((robot) => {
            let robotMesh = robotMeshesRef.current[robot.id];

            if (!robotMesh) {
                const group = new THREE.Group();

                // Base cylinder
                const baseGeo = new THREE.CylinderGeometry(0.85, 1.05, 0.55, 24);
                const baseMat = new THREE.MeshStandardMaterial({
                    color: robot.status === 'Moving' ? '#2563eb' : robot.status === 'Idle' ? '#0ea5e9' : '#f43f5e',
                    roughness: 0.25,
                    metalness: 0.7,
                });
                const base = new THREE.Mesh(baseGeo, baseMat);
                base.position.y = 0.28;
                base.castShadow = true;
                group.add(base);

                // LED beacon dome
                const domeGeo = new THREE.SphereGeometry(0.38, 16, 16);
                const domeMat = new THREE.MeshStandardMaterial({
                    color: '#ffffff',
                    emissive: robot.status === 'Moving' ? '#38bdf8' : '#38bdf8',
                    emissiveIntensity: 1.1,
                });
                const dome = new THREE.Mesh(domeGeo, domeMat);
                dome.position.y = 0.65;
                group.add(dome);

                // Glowing Halo ring
                const ringGeo = new THREE.RingGeometry(1.1, 1.35, 32);
                const ringMat = new THREE.MeshBasicMaterial({
                    color: '#38bdf8',
                    side: THREE.DoubleSide,
                    transparent: true,
                    opacity: 0.7,
                });
                const ring = new THREE.Mesh(ringGeo, ringMat);
                ring.rotation.x = -Math.PI / 2;
                ring.position.y = 0.05;
                group.add(ring);

                scene.add(group);
                robotMeshesRef.current[robot.id] = group;
                robotMesh = group;
            }

            const robotFloor = Number(robot.floor || 1);
            robotMesh.visible = robotFloor === activeFloor;

            const targetPos = coordTo3D(robot.current_x || 50, robot.current_y || 50, robotFloor);
            gsap.to(robotMesh.position, {
                x: targetPos.x,
                y: targetPos.y,
                z: targetPos.z,
                duration: 0.35,
                ease: 'linear',
            });
        });
    }, [robots, activeFloor]);

    return (
        <div className={`relative rounded-2xl overflow-hidden border border-blue-900/60 bg-slate-950 shadow-2xl ${className}`}>
            {/* Three.js Viewport */}
            <div ref={containerRef} className="w-full h-[540px] lg:h-[620px] cursor-grab active:cursor-grabbing" />

            {/* Loading Overlay */}
            {isLoading && (
                <div className="absolute inset-0 bg-slate-950/90 backdrop-blur-md flex flex-col items-center justify-center gap-3 z-30">
                    <div className="w-12 h-12 rounded-2xl bg-blue-600/20 border border-blue-500/40 flex items-center justify-center animate-spin">
                        <RefreshCw className="w-6 h-6 text-blue-400" />
                    </div>
                    <div className="text-center">
                        <p className="text-sm font-bold text-white">Loading 3D Digital Twin</p>
                        <p className="text-xs text-blue-300 mt-1">High-Precision Draco Floorplan ({loadProgress}%)</p>
                    </div>
                    <div className="w-52 h-2 bg-slate-800 rounded-full overflow-hidden mt-2 p-0.5 border border-blue-900/50">
                        <div
                            className="h-full bg-gradient-to-r from-blue-600 via-sky-400 to-white rounded-full transition-all duration-300 shadow-md shadow-blue-500/50"
                            style={{ width: `${loadProgress}%` }}
                        />
                    </div>
                </div>
            )}

            {/* Interactive Top-Left Overlay Controls (Blue & Clean Theme) */}
            <div className="absolute top-4 left-4 z-20 flex flex-wrap items-center gap-2">
                {/* Floor Switch Tabs */}
                <div className="p-1 bg-slate-900/90 backdrop-blur-md border border-blue-600/40 rounded-xl flex items-center gap-1 shadow-2xl shadow-blue-950/50">
                    <button
                        type="button"
                        onClick={() => onFloorChange && onFloorChange(1)}
                        className={`px-4 py-2 rounded-lg text-xs font-bold transition flex items-center gap-2 ${
                            activeFloor === 1
                                ? 'bg-blue-600 text-white shadow-lg shadow-blue-600/40 font-black'
                                : 'text-slate-300 hover:text-white hover:bg-slate-800/80'
                        }`}
                    >
                        <Layers className="w-3.5 h-3.5" />
                        Lantai 1
                    </button>
                    <button
                        type="button"
                        onClick={() => onFloorChange && onFloorChange(2)}
                        className={`px-4 py-2 rounded-lg text-xs font-bold transition flex items-center gap-2 ${
                            activeFloor === 2
                                ? 'bg-blue-600 text-white shadow-lg shadow-blue-600/40 font-black'
                                : 'text-slate-300 hover:text-white hover:bg-slate-800/80'
                        }`}
                    >
                        <Layers className="w-3.5 h-3.5" />
                        Lantai 2
                    </button>
                </div>

                {/* Toggle Room Names Sprite */}
                <button
                    type="button"
                    onClick={() => setShowRoomLabels(!showRoomLabels)}
                    className={`px-3 py-2 rounded-xl text-xs font-bold transition flex items-center gap-1.5 backdrop-blur-md border ${
                        showRoomLabels
                            ? 'bg-blue-600/20 border-blue-500/50 text-blue-200 hover:bg-blue-600/30'
                            : 'bg-slate-900/90 border-slate-700 text-slate-400 hover:text-slate-200'
                    }`}
                >
                    <Eye className="w-3.5 h-3.5 text-blue-400" />
                    <span>{showRoomLabels ? 'Nama Ruangan: ON' : 'Nama Ruangan: OFF'}</span>
                </button>

                {/* Reset Camera Focus */}
                <button
                    type="button"
                    onClick={() => focusFloorCamera(activeFloor, true)}
                    className="p-2 rounded-xl bg-slate-900/90 hover:bg-blue-600/20 border border-slate-700 hover:border-blue-500/50 text-slate-300 hover:text-white transition shadow-lg"
                    title="Focus Camera to Center"
                >
                    <Compass className="w-4 h-4 text-blue-400" />
                </button>
            </div>

            {/* Hovered Zone Pill */}
            {hoveredRoom && (
                <div className="absolute top-4 right-4 z-20 px-4 py-2 rounded-xl bg-slate-900/95 backdrop-blur-md border border-blue-500/50 text-xs font-bold text-white shadow-2xl flex items-center gap-2 animate-fade-in">
                    <span className="w-2 h-2 rounded-full bg-blue-400 animate-ping" />
                    <span>Zona: {hoveredRoom.replace(/_/g, ' ')}</span>
                </div>
            )}

            {/* Bottom HUD Legend & Controls Guide */}
            <div className="absolute bottom-4 left-4 right-4 z-20 flex items-center justify-between pointer-events-none">
                <div className="pointer-events-auto px-4 py-2 rounded-xl bg-slate-900/90 backdrop-blur-md border border-blue-900/50 text-[11px] text-slate-300 flex items-center gap-4 shadow-xl">
                    <span className="flex items-center gap-1.5 text-white font-semibold">
                        <span className="w-2.5 h-2.5 rounded-full bg-blue-500 inline-block shadow-sm shadow-blue-500" /> Moving
                    </span>
                    <span className="flex items-center gap-1.5 text-white font-semibold">
                        <span className="w-2.5 h-2.5 rounded-full bg-sky-400 inline-block" /> Idle
                    </span>
                    <span className="flex items-center gap-1.5 text-white font-semibold">
                        <span className="w-2.5 h-2.5 rounded-full bg-rose-500 inline-block" /> Issue
                    </span>
                </div>

                <div className="pointer-events-auto text-[11px] text-slate-300 bg-slate-900/90 backdrop-blur-md px-4 py-2 rounded-xl border border-blue-900/50 shadow-xl hidden sm:block">
                    Orbit: <kbd className="px-1.5 py-0.5 bg-blue-950 border border-blue-800 rounded text-blue-200 font-mono">Left Click</kbd> | Pan:{' '}
                    <kbd className="px-1.5 py-0.5 bg-blue-950 border border-blue-800 rounded text-blue-200 font-mono">Right Click</kbd> | Zoom:{' '}
                    <kbd className="px-1.5 py-0.5 bg-blue-950 border border-blue-800 rounded text-blue-200 font-mono">Scroll</kbd>
                </div>
            </div>
        </div>
    );
}
