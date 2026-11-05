const canvas = document.getElementById('magnetic-grid');
const ctx = canvas.getContext('2d');
let width, height;
let cols, rows;
const spacing = 50; 
let nodes = [];

let mouse = { x: -1000, y: -1000, radius: 200 }; 

const colors = [
    { c: '#00FFFF', offsetMult: 1 },    
    { c: '#FF00FF', offsetMult: 0.2 },  
    { c: '#FFFF00', offsetMult: -1 }    
];

function init() {
    width = canvas.width = window.innerWidth;
    height = canvas.height = window.innerHeight;
    cols = Math.ceil(width / spacing) + 2;
    rows = Math.ceil(height / spacing) + 2;
    nodes = [];
    for (let i = 0; i < cols; i++) {
        nodes[i] = [];
        for (let j = 0; j < rows; j++) {
            nodes[i][j] = {
                baseX: i * spacing,
                baseY: j * spacing,
                x: i * spacing,
                y: j * spacing,
                vx: 0,
                vy: 0
            };
        }
    }
}

window.addEventListener('resize', init);
window.addEventListener('mousemove', (e) => { mouse.x = e.clientX; mouse.y = e.clientY; });
window.addEventListener('touchmove', (e) => { mouse.x = e.touches[0].clientX; mouse.y = e.touches[0].clientY; });
window.addEventListener('mouseout', () => { mouse.x = -1000; mouse.y = -1000; });
window.addEventListener('touchend', () => { mouse.x = -1000; mouse.y = -1000; });

function update() {
    ctx.clearRect(0, 0, width, height);
    for (let i = 0; i < cols; i++) {
        for (let j = 0; j < rows; j++) {
            let node = nodes[i][j]; let dx = mouse.x - node.baseX; let dy = mouse.y - node.baseY; let dist = Math.sqrt(dx*dx + dy*dy);
            let targetX = node.baseX; let targetY = node.baseY;
            if (dist < mouse.radius) { let f = (mouse.radius - dist) / mouse.radius; f *= f; targetX -= (dx/dist)*f*50; targetY -= (dy/dist)*f*50; }
            node.vx += (targetX - node.x) * 0.15; node.vy += (targetY - node.y) * 0.15;
            node.vx *= 0.65; node.vy *= 0.65; node.x += node.vx; node.y += node.vy;
        }
    }
    ctx.lineWidth = 1; ctx.globalCompositeOperation = 'multiply'; 
    colors.forEach(colorObj => {
        ctx.strokeStyle = colorObj.c; ctx.beginPath();
        for (let j = 0; j < rows; j++) { for (let i = 0; i < cols; i++) {
            let node = nodes[i][j]; let dx = node.x - mouse.x; let dy = node.y - mouse.y; let dist = Math.sqrt(dx*dx + dy*dy);
            let intensity = Math.max(0, 1 - dist / (mouse.radius * 1.2)); let ox = (dx / (dist || 1)) * intensity * 8 * colorObj.offsetMult; let oy = (dy / (dist || 1)) * intensity * 8 * colorObj.offsetMult;
            if (i === 0) ctx.moveTo(node.x + ox, node.y + oy); else ctx.lineTo(node.x + ox, node.y + oy);
        }}
        for (let i = 0; i < cols; i++) { for (let j = 0; j < rows; j++) {
            let node = nodes[i][j]; let dx = node.x - mouse.x; let dy = node.y - mouse.y; let dist = Math.sqrt(dx*dx + dy*dy);
            let intensity = Math.max(0, 1 - dist / (mouse.radius * 1.2)); let ox = (dx / (dist || 1)) * intensity * 8 * colorObj.offsetMult; let oy = (dy / (dist || 1)) * intensity * 8 * colorObj.offsetMult;
            if (j === 0) ctx.moveTo(node.x + ox, node.y + oy); else ctx.lineTo(node.x + ox, node.y + oy);
        }}
        ctx.stroke();
    });
    ctx.globalCompositeOperation = 'source-over';
    requestAnimationFrame(update);
}

init();
update();
