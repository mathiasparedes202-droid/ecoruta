export default function RouteCard({ route }) {
  return (
    <article className="route-card">
      <div className="route-card__topline">
        <span className={`difficulty difficulty--${route.difficulty}`}>{route.difficulty}</span>
        <span>{route.distanceKm} km</span>
      </div>
      <h3>{route.name}</h3>
      <p>{route.description}</p>
      <button type="button" className="route-card__action">Ver  <span aria-hidden="true">-&gt;</span></button>
    </article>
  );
}
