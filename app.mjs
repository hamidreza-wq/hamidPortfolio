import { craft, profile, projects } from "./portfolio-data.mjs";

const projectGrid = document.querySelector("#project-grid");
const searchInput = document.querySelector(".project-search input");
const emptyState = document.querySelector(".empty-state");
const dialog = document.querySelector(".project-dialog");
const dialogContent = document.querySelector(".dialog-content");
const reducedMotion = window.matchMedia("(prefers-reduced-motion: reduce)").matches;
let activeFilter = "all";

function escapeHTML(value) {
  return String(value).replace(/[&<>"']/g, (character) => ({
    "&": "&amp;",
    "<": "&lt;",
    ">": "&gt;",
    '"': "&quot;",
    "'": "&#39;",
  })[character]);
}

function renderProjects() {
  const query = searchInput.value.trim().toLowerCase();
  const visibleProjects = projects.filter((project) => {
    const matchesFilter = activeFilter === "all" || project.category === activeFilter;
    const searchable = [
      project.name,
      project.description,
      project.categoryLabel,
      ...project.technologies,
    ].join(" ").toLowerCase();
    return matchesFilter && searchable.includes(query);
  });

  projectGrid.innerHTML = visibleProjects.map((project, index) => `
    <article class="project-card reveal is-visible" style="--card-index:${index}">
      <button class="project-open" type="button" data-project="${escapeHTML(project.repo)}" aria-label="Open details for ${escapeHTML(project.name)}">
        <span class="project-art project-art--${escapeHTML(project.visual)}" aria-hidden="true">
          <span class="art-orbit art-orbit--one"></span>
          <span class="art-orbit art-orbit--two"></span>
          <span class="art-core"></span>
          <span class="art-caption">${escapeHTML(project.visualLabel)}</span>
          <span class="art-index">0${index + 1}</span>
        </span>
        <span class="project-card-body">
          <span class="project-category">${escapeHTML(project.categoryLabel)}</span>
          <span class="project-title-line"><strong>${escapeHTML(project.name)}</strong><span class="project-arrow" aria-hidden="true">↗</span></span>
          <span class="project-description">${escapeHTML(project.description)}</span>
          <span class="project-tags">${project.technologies.slice(0, 4).map((tech) => `<span>${escapeHTML(tech)}</span>`).join("")}</span>
        </span>
      </button>
    </article>
  `).join("");
  emptyState.hidden = visibleProjects.length > 0;
  projectGrid.hidden = visibleProjects.length === 0;
}

function showProject(project) {
  dialogContent.innerHTML = `
    <p class="eyebrow">${escapeHTML(project.categoryLabel)} <span class="dialog-index">/ REPOSITORY NOTES</span></p>
    <h2 id="dialog-title">${escapeHTML(project.name)}</h2>
    <p class="dialog-description">${escapeHTML(project.detail)}</p>
    <h3>Built with</h3>
    <ul class="dialog-tags">${project.technologies.map((tech) => `<li>${escapeHTML(tech)}</li>`).join("")}</ul>
    <h3>Inside the project</h3>
    <ul class="dialog-features">${project.features.map((feature) => `<li>${escapeHTML(feature)}</li>`).join("")}</ul>
    <a class="button button-primary dialog-repo" href="https://github.com/hamidreza-wq/${encodeURIComponent(project.repo)}" target="_blank" rel="noreferrer">
      View repository <span aria-hidden="true">↗</span>
    </a>
  `;
  dialog.showModal();
}

document.querySelectorAll(".filter-button").forEach((button) => {
  button.addEventListener("click", () => {
    activeFilter = button.dataset.filter;
    document.querySelectorAll(".filter-button").forEach((filterButton) => {
      const selected = filterButton === button;
      filterButton.classList.toggle("is-active", selected);
      filterButton.setAttribute("aria-pressed", String(selected));
    });
    renderProjects();
  });
});

searchInput.addEventListener("input", renderProjects);
projectGrid.addEventListener("click", (event) => {
  const trigger = event.target.closest("[data-project]");
  if (!trigger) return;
  const project = projects.find((item) => item.repo === trigger.dataset.project);
  if (project) showProject(project);
});

document.querySelector(".dialog-close").addEventListener("click", () => dialog.close());
dialog.addEventListener("keydown", (event) => {
  if (event.key !== "Escape") return;
  event.preventDefault();
  dialog.close();
});
dialog.addEventListener("click", (event) => {
  if (event.target === dialog) dialog.close();
});

document.querySelector("#craft-groups").innerHTML = craft.map((group, index) => `
  <section class="craft-group reveal" style="--group-index:${index}">
    <h3><span>0${index + 1}</span>${escapeHTML(group.title)}</h3>
    <ul>${group.items.map((item) => `<li>${escapeHTML(item)}</li>`).join("")}</ul>
  </section>
`).join("");

const contactActions = document.querySelector("#contact-actions");
contactActions.innerHTML = `
  ${profile.email ? `<a class="button button-primary" href="mailto:${encodeURIComponent(profile.email)}">Email me <span aria-hidden="true">↗</span></a>` : ""}
  <a class="button ${profile.email ? "button-quiet" : "button-primary"}" href="${escapeHTML(profile.github)}" target="_blank" rel="noreferrer">
    Connect on GitHub <span aria-hidden="true">↗</span>
  </a>
  ${profile.linkedin ? `<a class="button button-quiet" href="${escapeHTML(profile.linkedin)}" target="_blank" rel="noreferrer">LinkedIn <span aria-hidden="true">↗</span></a>` : ""}
`;

renderProjects();

const menuToggle = document.querySelector(".menu-toggle");
const navigation = document.querySelector(".primary-navigation");
menuToggle.addEventListener("click", () => {
  const isExpanded = menuToggle.getAttribute("aria-expanded") === "true";
  menuToggle.setAttribute("aria-expanded", String(!isExpanded));
  menuToggle.setAttribute("aria-label", isExpanded ? "Open navigation" : "Close navigation");
  navigation.classList.toggle("is-open", !isExpanded);
});

navigation.addEventListener("click", (event) => {
  if (event.target.closest("a")) {
    menuToggle.setAttribute("aria-expanded", "false");
    menuToggle.setAttribute("aria-label", "Open navigation");
    navigation.classList.remove("is-open");
  }
});

const progressBar = document.querySelector(".scroll-progress");
let scrollFrame = 0;
window.addEventListener("scroll", () => {
  if (scrollFrame) return;
  scrollFrame = window.requestAnimationFrame(() => {
    const scrollable = document.documentElement.scrollHeight - window.innerHeight;
    progressBar.style.transform = `scaleX(${scrollable > 0 ? window.scrollY / scrollable : 0})`;
    scrollFrame = 0;
  });
}, { passive: true });

const revealObserver = new IntersectionObserver((entries, observer) => {
  entries.forEach((entry) => {
    if (!entry.isIntersecting) return;
    entry.target.classList.add("is-visible");
    observer.unobserve(entry.target);
  });
}, { threshold: 0.14 });

if (!reducedMotion) {
  document.querySelectorAll(".reveal:not(.is-visible)").forEach((element) => revealObserver.observe(element));
} else {
  document.querySelectorAll(".reveal").forEach((element) => element.classList.add("is-visible"));
}

const canvas = document.querySelector(".particle-field");
const context = canvas.getContext("2d", { alpha: true });
const scene = document.querySelector(".universe-scene");
const pointer = { x: 0, y: 0, targetX: 0, targetY: 0 };
let particles = [];
let animationFrame = 0;
let width = 0;
let height = 0;

function resizeCanvas() {
  const ratio = Math.min(window.devicePixelRatio || 1, 1.5);
  width = window.innerWidth;
  height = window.innerHeight;
  canvas.width = Math.round(width * ratio);
  canvas.height = Math.round(height * ratio);
  canvas.style.width = `${width}px`;
  canvas.style.height = `${height}px`;
  context.setTransform(ratio, 0, 0, ratio, 0, 0);

  const count = Math.min(reducedMotion ? 36 : 90, Math.round(width * height / 14500));
  particles = Array.from({ length: count }, () => ({
    x: Math.random() * width,
    y: Math.random() * height,
    radius: Math.random() * 1.25 + 0.25,
    alpha: Math.random() * 0.5 + 0.15,
    drift: (Math.random() - 0.5) * 0.12,
    phase: Math.random() * Math.PI * 2,
  }));
  if (reducedMotion) drawParticles(0);
}

function drawParticles(time) {
  pointer.x += (pointer.targetX - pointer.x) * 0.04;
  pointer.y += (pointer.targetY - pointer.y) * 0.04;
  scene.style.setProperty("--pointer-x", `${pointer.x.toFixed(3)}`);
  scene.style.setProperty("--pointer-y", `${pointer.y.toFixed(3)}`);
  context.clearRect(0, 0, width, height);
  particles.forEach((particle, index) => {
    const shimmer = reducedMotion ? 1 : 0.72 + Math.sin(time / 1300 + particle.phase) * 0.28;
    const shiftX = pointer.x * (index % 4 + 1) * 0.8;
    const shiftY = pointer.y * (index % 3 + 1) * 0.65;
    particle.y += reducedMotion ? 0 : particle.drift;
    if (particle.y > height + 2) particle.y = -2;
    if (particle.y < -2) particle.y = height + 2;
    context.beginPath();
    context.fillStyle = `rgba(212, 202, 255, ${particle.alpha * shimmer})`;
    context.arc(particle.x + shiftX, particle.y + shiftY, particle.radius, 0, Math.PI * 2);
    context.fill();
  });
  if (!reducedMotion && !document.hidden) animationFrame = requestAnimationFrame(drawParticles);
}

function setPointer(event) {
  pointer.targetX = (event.clientX / window.innerWidth - 0.5) * 2;
  pointer.targetY = (event.clientY / window.innerHeight - 0.5) * 2;
}

if (context) {
  resizeCanvas();
  if (!reducedMotion) animationFrame = requestAnimationFrame(drawParticles);
  window.addEventListener("resize", resizeCanvas, { passive: true });
  window.addEventListener("pointermove", setPointer, { passive: true });
  document.addEventListener("visibilitychange", () => {
    if (document.hidden) cancelAnimationFrame(animationFrame);
    else if (!reducedMotion) animationFrame = requestAnimationFrame(drawParticles);
  });
}
