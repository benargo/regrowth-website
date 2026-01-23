export default function ClassIcon({ wowClass }) {
    return (
        <img
            src="../../images/classicons.png"
            alt={`${wowClass.name} Class icon`}
            className={`class-icon class-icon-${wowClass.name} ${!wowClass.is_recruiting ? 'class-icon-closed' : ''}`}
            title={wowClass.display_name}
        />
    );
}
